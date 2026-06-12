<?php

namespace App\Service\WhatsApp;

use App\Service\Appointment\AppointmentBookingService;
use Psr\Log\LoggerInterface;
use App\Service\Appointment\AppointmentCancellationService;

final class WhatsAppBotOrchestrator
{
    public function __construct(
        private readonly WhatsAppClient $whatsAppClient,
        private readonly IntentClassifier $intentClassifier,
        private readonly WhatsAppCatalogService $catalogService,
        private readonly WhatsAppConversationStateService $conversationStateService,
        private readonly AppointmentBookingService $appointmentBookingService,
        private readonly AppointmentCancellationService $appointmentCancellationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handleIncomingMessage(array $message, array $payload): void
    {
        $from = $message['from'] ?? null;
        $body = trim((string) ($message['body'] ?? ''));

        if (!$from) {
            return;
        }

        $normalizedBody = mb_strtolower(trim($body));

        try {
            $state = $this->conversationStateService->getState($from);

            if ($state !== null && $state['step'] === 'cancelling_waiting_folio') {
                $this->handleCancellationFolio($from, $normalizedBody);

                return;
            }

            if ($state !== null && $state['step'] === 'cancelling_confirming') {
                $this->handleCancellationConfirmation($from, $normalizedBody, $state);

                return;
            }

            if ($this->shouldStartCancellationFlow($normalizedBody)) {
                $folio = $this->extractAppointmentFolioFromText($body);

                if ($folio !== null) {
                    $this->startCancellationFlowWithFolio($from, $folio);

                    return;
                }

                $this->startCancellationFlow($from);

                return;
            }

            if ($state !== null && in_array($normalizedBody, ['cancelar', 'cancel', 'salir'], true)) {
                $this->conversationStateService->clear($from);

                $this->whatsAppClient->sendTextMessage(
                    $from,
                    "Listo, cancelé este flujo de agenda.\n\nPuedes escribir *agenda* cuando quieras comenzar de nuevo."
                );

                return;
            }

            if ($state !== null && $state['step'] === 'selecting_branch') {
                $this->handleBranchSelection($from, $body, $state);

                return;
            }

            if ($state !== null && $state['step'] === 'selecting_service') {
                $this->handleServiceSelection($from, $body, $state);

                return;
            }

            if ($state !== null && $state['step'] === 'selecting_date') {
                $this->handleDateSelection($from, $normalizedBody, $state);

                return;
            }

            if ($state !== null && $state['step'] === 'selecting_barber') {
                $this->handleBarberSelection($from, $normalizedBody, $state);

                return;
            }

            if ($state !== null && $state['step'] === 'selecting_time') {
                $this->handleTimeSelection($from, $normalizedBody, $state);

                return;
            }

            if ($state !== null && $state['step'] === 'collecting_customer_name') {
                $this->handleCustomerName($from, $body, $state);

                return;
            }

            if ($state !== null && $state['step'] === 'collecting_customer_email') {
                $this->handleCustomerEmail($from, $body, $state);

                return;
            }

            if ($state !== null && $state['step'] === 'collecting_customer_phone') {
                $this->handleCustomerPhone($from, $body, $state);

                return;
            }

            if ($state !== null && $state['step'] === 'collecting_customer_notes') {
                $this->handleCustomerNotes($from, $body, $state);

                return;
            }

            if ($state !== null && $state['step'] === 'confirming_booking') {
                $this->handleBookingConfirmation($from, $normalizedBody, $state);

                return;
            }

            if ($this->shouldStartBookingFlow($normalizedBody)) {
                $this->startBookingFlow($from, $body);

                return;
            }

            $intent = $this->intentClassifier->detect($body);

            if ($intent === 'cancel_appointment') {
                $folio = $this->extractAppointmentFolioFromText($body);

                if ($folio !== null) {
                    $this->startCancellationFlowWithFolio($from, $folio);

                    return;
                }

                $responseText = $this->startCancellationFlowAndReturnMessage($from);
            } else {
                $responseText = match ($intent) {
                    'greeting' => $this->mainMenu(),
                    'check_agenda' => $this->startBookingFlowAndReturnMessage($from, $body),
                    'check_barbers' => $this->barbersResponse(),
                    'reschedule' => $this->rescheduleResponse(),
                    'list_services' => $this->startBookingFlowAndReturnMessage($from, $body),
                    'product_recommendation' => $this->productsResponse($body),
                    'haircut_recommendation' => $this->haircutRecommendationResponse($body),
                    default => $this->unknownResponse(),
                };
            }

            $result = $this->whatsAppClient->sendTextMessage($from, $responseText);

            $this->logger->info('WhatsApp bot response sent', [
                'to' => $from,
                'body' => $body,
                'intent' => $intent,
                'result' => $result,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error(sprintf(
                'Error procesando mensaje de WhatsApp: %s | class=%s | file=%s | line=%d',
                $exception->getMessage(),
                $exception::class,
                $exception->getFile(),
                $exception->getLine()
            ), [
                'message' => $message,
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->whatsAppClient->sendTextMessage(
                $from,
                "Tuve un problema procesando tu mensaje.\n\nPor favor intenta de nuevo o escribe *agenda* para comenzar otra vez."
            );
        }
    }

    private function shouldStartBookingFlow(string $normalizedBody): bool
    {
        return in_array($normalizedBody, [
                'agenda',
                'agendar',
                'cita',
                'quiero una cita',
                'quiero agendar',
                'servicio',
                'servicios',
                'ver servicios',
            ], true)
            || str_contains($normalizedBody, 'quiero una cita')
            || str_contains($normalizedBody, 'quiero agendar')
            || str_contains($normalizedBody, 'agendar cita')
            || str_contains($normalizedBody, 'hacer cita')
            || str_contains($normalizedBody, 'servicio')
            || str_contains($normalizedBody, 'servicios');
    }

    private function startBookingFlow(string $from, ?string $message = null): void
    {
        $state = $this->conversationStateService->start($from, 1);
        $state = $this->applyBookingHintsFromMessage($from, $state, $message);

        $branches = $this->catalogService->getBranchesByCompany((int) $state['company_id']);

        $messageText = $this->catalogService->formatBranchesMenu($branches);

        if (!empty($state['date'])) {
            $messageText = sprintf(
                "Detecté que quieres agendar para *%s*.

%s",
                $this->formatDateHintForCustomer((string) $state['date']),
                $messageText
            );
        }

        $this->whatsAppClient->sendTextMessage(
            $from,
            $messageText
        );
    }

    private function startBookingFlowAndReturnMessage(string $from, ?string $message = null): string
    {
        $state = $this->conversationStateService->start($from, 1);
        $state = $this->applyBookingHintsFromMessage($from, $state, $message);

        $branches = $this->catalogService->getBranchesByCompany((int) $state['company_id']);
        $messageText = $this->catalogService->formatBranchesMenu($branches);

        if (!empty($state['date'])) {
            $messageText = sprintf(
                "Detecté que quieres agendar para *%s*.

%s",
                $this->formatDateHintForCustomer((string) $state['date']),
                $messageText
            );
        }

        return $messageText;
    }

    private function applyBookingHintsFromMessage(string $from, array $state, ?string $message): array
    {
        if ($message === null || trim($message) === '') {
            return $state;
        }

        $date = $this->parseBookingDate($message);

        if ($date === null || $this->isPastBookingDate($date)) {
            return $state;
        }

        return $this->conversationStateService->updateState(
            $from,
            (string) ($state['step'] ?? 'selecting_branch'),
            [
                'date' => $date,
                'date_text' => $this->extractBookingDateTextFromMessage($message) ?? $date,
            ]
        );
    }

    private function handleBranchSelection(string $from, string $message, array $state): void
    {
        $normalizedBody = mb_strtolower(trim($message));
        $companyId = (int) $state['company_id'];

        if (ctype_digit($normalizedBody)) {
            $branch = $this->catalogService->getBranchByOption($companyId, (int) $normalizedBody);
        } else {
            $branch = $this->catalogService->getBranchByText($companyId, $message);
        }

        if ($branch === null) {
            $branches = $this->catalogService->getBranchesByCompany($companyId);

            $this->whatsAppClient->sendTextMessage(
                $from,
                "No encontré esa sucursal. Puedes responder con el número o con el nombre.\n\n" . $this->catalogService->formatBranchesMenu($branches)
            );

            return;
        }

        $branchStateData = [
            'branch_id' => (int) $branch['id'],
            'branch_name' => (string) $branch['name'],
            'branch_address' => (string) ($branch['address'] ?? ''),
        ];

        if (!empty($state['date'])) {
            $branchStateData['date'] = (string) $state['date'];
        }

        if (!empty($state['date_text'])) {
            $branchStateData['date_text'] = (string) $state['date_text'];
        }

        $this->conversationStateService->updateState(
            $from,
            'selecting_service',
            $branchStateData
        );

        $services = $this->catalogService->getServicesByBranch((int) $branch['id']);

        $this->whatsAppClient->sendTextMessage(
            $from,
            $this->catalogService->formatServicesMenu((string) $branch['name'], $services)
        );
    }

    private function handleServiceSelection(string $from, string $message, array $state): void
    {
        $normalizedBody = mb_strtolower(trim($message));
        $branchId = (int) $state['branch_id'];

        if (ctype_digit($normalizedBody)) {
            $service = $this->catalogService->getServiceByOption($branchId, (int) $normalizedBody);
        } else {
            $service = $this->catalogService->getServiceByText($branchId, $message);
        }

        if ($service === null) {
            $services = $this->catalogService->getServicesByBranch($branchId);

            $this->whatsAppClient->sendTextMessage(
                $from,
                "No encontré ese servicio. Puedes responder con el número o con el nombre del servicio.\n\n" . $this->catalogService->formatServicesMenu((string) $state['branch_name'], $services)
            );

            return;
        }

        $serviceStateData = [
            'service_id' => (int) $service['id'],
            'service_name' => (string) $service['name'],
            'service_price' => (float) $service['price'],
            'service_duration' => (int) $service['duration'],
        ];

        if (!empty($state['date'])) {
            $serviceStateData['date'] = (string) $state['date'];
        }

        if (!empty($state['date_text'])) {
            $serviceStateData['date_text'] = (string) $state['date_text'];
        }

        $updatedState = $this->conversationStateService->updateState(
            $from,
            'selecting_date',
            $serviceStateData
        );

        if (!empty($updatedState['date'])) {
            $this->handleDateSelection($from, (string) $updatedState['date'], $updatedState);

            return;
        }

        $this->whatsAppClient->sendTextMessage(
            $from,
            sprintf(
                "Perfecto. Seleccionaste *%s*.\n\n¿Para qué día quieres tu cita?\n\nPuedes escribir:\n*hoy*\n*mañana*\n*18/06/2026*\n*18 de junio*\n*para el 18*",
                (string) $service['name']
            )
        );
    }

    private function handleDateSelection(string $from, string $normalizedBody, array $state): void
    {
        $date = $this->parseBookingDate($normalizedBody);

        if ($date === null) {
            $this->whatsAppClient->sendTextMessage(
                $from,
                "No pude entender la fecha.\n\nPuedes escribir:\n*hoy*\n*mañana*\n*18/06/2026*"
            );

            return;
        }

        if ($this->isPastBookingDate($date)) {
            $this->conversationStateService->updateState(
                $from,
                'selecting_date',
                [
                    'date' => null,
                ]
            );

            $this->whatsAppClient->sendTextMessage(
                $from,
                "Esa fecha ya pasó y no puedo agendar citas en días anteriores.\n\nPuedes escribir:\n*hoy*\n*mañana*\n*18/06/2026*"
            );

            return;
        }

        if (empty($state['branch_id']) || empty($state['service_id'])) {
            $this->conversationStateService->clear($from);

            $this->whatsAppClient->sendTextMessage(
                $from,
                "Perdí algunos datos de la cita. Vamos a iniciar de nuevo.\n\nEscribe *agenda* para comenzar."
            );

            return;
        }

        $branchId = (int) $state['branch_id'];
        $serviceId = (int) $state['service_id'];
        $branchName = (string) $state['branch_name'];
        $serviceName = (string) $state['service_name'];

        $barbers = $this->catalogService->getAvailableBarbers(
            $branchId,
            $date,
            $serviceId
        );

        if ($barbers === []) {
            $this->conversationStateService->updateState(
                $from,
                'selecting_date',
                [
                    'date' => $date,
                ]
            );

            $this->whatsAppClient->sendTextMessage(
                $from,
                $this->catalogService->formatAvailableBarbersMenu(
                    $branchName,
                    $serviceName,
                    $date,
                    $barbers
                )
            );

            return;
        }

        $this->conversationStateService->updateState(
            $from,
            'selecting_barber',
            [
                'date' => $date,
            ]
        );

        $this->whatsAppClient->sendTextMessage(
            $from,
            $this->catalogService->formatAvailableBarbersMenu(
                $branchName,
                $serviceName,
                $date,
                $barbers
            )
        );
    }

    private function handleBarberSelection(string $from, string $normalizedBody, array $state): void
    {
        if (ctype_digit($normalizedBody)) {
            $barber = $this->catalogService->getAvailableBarberByOption(
                (int) $state['branch_id'],
                (string) $state['date'],
                (int) $state['service_id'],
                (int) $normalizedBody
            );
        } else {
            $barber = $this->catalogService->getAvailableBarberByText(
                (int) $state['branch_id'],
                (string) $state['date'],
                (int) $state['service_id'],
                $normalizedBody
            );
        }

        if ($barber === null) {
            $barbers = $this->catalogService->getAvailableBarbers(
                (int) $state['branch_id'],
                (string) $state['date'],
                (int) $state['service_id']
            );

            $this->whatsAppClient->sendTextMessage(
                $from,
                "No encontré ese barbero. Puedes responder con el número o con el nombre.\n\n" . $this->catalogService->formatAvailableBarbersMenu(
                    (string) $state['branch_name'],
                    (string) $state['service_name'],
                    (string) $state['date'],
                    $barbers
                )
            );

            return;
        }

        $slotGroups = $this->catalogService->getAvailableSlots(
            (int) $barber['id'],
            (int) $state['branch_id'],
            (string) $state['date'],
            (int) $state['service_id']
        );

        if ($slotGroups === []) {
            $this->conversationStateService->updateState(
                $from,
                'selecting_barber',
                [
                    'barber_id' => (int) $barber['id'],
                    'barber_name' => (string) $barber['name'],
                ]
            );

            $this->whatsAppClient->sendTextMessage(
                $from,
                sprintf(
                    "Seleccionaste a *%s*, pero por el momento no encontré horarios disponibles para el *%s*.\n\nPuedes intentar con otra fecha escribiendo, por ejemplo:\n*mañana*\n*18/06/2026*",
                    (string) $barber['name'],
                    (string) $state['date']
                )
            );

            return;
        }

        $this->conversationStateService->updateState(
            $from,
            'selecting_time',
            [
                'barber_id' => (int) $barber['id'],
                'barber_name' => (string) $barber['name'],
            ]
        );

        $this->whatsAppClient->sendTextMessage(
            $from,
            $this->catalogService->formatAvailableSlotsMenu(
                (string) $barber['name'],
                (string) $state['date'],
                $slotGroups
            )
        );
    }

    private function handleTimeSelection(string $from, string $normalizedBody, array $state): void
    {
        if (ctype_digit($normalizedBody)) {
            $slot = $this->catalogService->getSlotByOption(
                (int) $state['barber_id'],
                (int) $state['branch_id'],
                (string) $state['date'],
                (int) $state['service_id'],
                (int) $normalizedBody
            );
        } else {
            $slot = $this->catalogService->getSlotByTimeText(
                (int) $state['barber_id'],
                (int) $state['branch_id'],
                (string) $state['date'],
                (int) $state['service_id'],
                $normalizedBody
            );
        }

        if ($slot === null) {
            $slotGroups = $this->catalogService->getAvailableSlots(
                (int) $state['barber_id'],
                (int) $state['branch_id'],
                (string) $state['date'],
                (int) $state['service_id']
            );

            $this->whatsAppClient->sendTextMessage(
                $from,
                "No encontré ese horario. Puedes responder con el número o con una hora como *5 pm*, *17:30* o *a las 5*.\n\n" . $this->catalogService->formatAvailableSlotsMenu(
                    (string) $state['barber_name'],
                    (string) $state['date'],
                    $slotGroups
                )
            );

            return;
        }

        $timeLabel = (string) $slot['time'];
        $scheduledDateTime = $this->catalogService->buildScheduledDateTime(
            (string) $state['date'],
            $timeLabel
        );

        $this->conversationStateService->updateState(
            $from,
            'collecting_customer_name',
            [
                'time_label' => $timeLabel,
                'scheduled_date_time' => $scheduledDateTime,
            ]
        );

        $this->whatsAppClient->sendTextMessage(
            $from,
            sprintf(
                "Perfecto. Apartamos este horario:\n\nSucursal: *%s*\nServicio: *%s*\nBarbero: *%s*\nFecha: *%s*\nHorario: *%s*\n\nPara continuar, escribe tu *nombre completo*.",
                (string) $state['branch_name'],
                (string) $state['service_name'],
                (string) $state['barber_name'],
                (string) $state['date'],
                $timeLabel
            )
        );
    }

    private function handleCustomerName(string $from, string $body, array $state): void
    {
        $customerName = trim($body);

        if (mb_strlen($customerName) < 3) {
            $this->whatsAppClient->sendTextMessage(
                $from,
                "El nombre parece muy corto.\n\nPor favor escribe tu *nombre completo*."
            );

            return;
        }

        $this->conversationStateService->updateState(
            $from,
            'collecting_customer_email',
            [
                'customer_name' => $customerName,
            ]
        );

        $this->whatsAppClient->sendTextMessage(
            $from,
            sprintf(
                "Gracias, *%s*.\n\nAhora escribe tu *correo electrónico* para registrar la cita.",
                $customerName
            )
        );
    }

    private function handleCustomerEmail(string $from, string $body, array $state): void
    {
        $email = mb_strtolower(trim($body));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->whatsAppClient->sendTextMessage(
                $from,
                "El correo no parece válido.\n\nPor favor escribe un correo como:\ncliente@email.com"
            );

            return;
        }

        $this->conversationStateService->updateState(
            $from,
            'collecting_customer_phone',
            [
                'customer_email' => $email,
            ]
        );

        $suggestedPhone = $this->extractPhoneFromWhatsAppId($from);

        $this->whatsAppClient->sendTextMessage(
            $from,
            sprintf(
                "Correo registrado: %s.\n\nAhora escribe tu *teléfono a 10 dígitos*.\n\nDetecté este número de WhatsApp: *%s*\nPuedes responder con ese número o escribir otro.",
                $email,
                $suggestedPhone
            )
        );
    }

    private function handleCustomerPhone(string $from, string $body, array $state): void
    {
        $phone = preg_replace('/\D+/', '', $body) ?? '';

        if (str_starts_with($phone, '52') && strlen($phone) > 10) {
            $phone = substr($phone, -10);
        }

        if (strlen($phone) !== 10) {
            $this->whatsAppClient->sendTextMessage(
                $from,
                "El teléfono debe tener 10 dígitos.\n\nEjemplo:\n8180201499"
            );

            return;
        }

        $this->conversationStateService->updateState(
            $from,
            'collecting_customer_notes',
            [
                'customer_phone' => $phone,
                'customer_country_code' => '+52',
            ]
        );

        $this->whatsAppClient->sendTextMessage(
            $from,
            "¿Deseas agregar alguna nota para tu cita?\n\nPuedes escribir tu nota o responder:\n*sin notas*"
        );
    }

    private function handleCustomerNotes(string $from, string $body, array $state): void
    {
        $notes = trim($body);
        $normalizedNotes = mb_strtolower($notes);

        if ($notes === '' || in_array($normalizedNotes, ['sin notas', 'no', 'ninguna', 'nada'], true)) {
            $notes = '';
        }

        $updatedState = $this->conversationStateService->updateState(
            $from,
            'confirming_booking',
            [
                'customer_notes' => $notes,
            ]
        );

        $this->whatsAppClient->sendTextMessage(
            $from,
            $this->formatBookingConfirmation($updatedState)
        );
    }

    private function handleBookingConfirmation(string $from, string $normalizedBody, array $state): void
    {
        if (in_array($normalizedBody, ['confirmar', 'confirmo', 'si', 'sí', 'ok'], true)) {
            try {
                $result = $this->appointmentBookingService->bookFromWhatsAppState($state);

                $this->conversationStateService->clear($from);

                $this->whatsAppClient->sendTextMessage(
                    $from,
                    sprintf(
                        "Tu cita quedó reservada con éxito.\n\nFolio: *%s*\nSucursal: *%s*\nServicio: *%s*\nBarbero: *%s*\nFecha: *%s*\nHorario: *%s*\n\nTe esperamos.",
                        (string) ($result['appointmentId'] ?? ''),
                        (string) ($state['branch_name'] ?? ''),
                        (string) ($state['service_name'] ?? ''),
                        (string) ($state['barber_name'] ?? ''),
                        (string) ($state['date'] ?? ''),
                        (string) ($state['time_label'] ?? '')
                    )
                );

                return;
            } catch (\InvalidArgumentException $exception) {
                $this->logger->warning('Datos incompletos para reservar cita desde WhatsApp', [
                    'wa_id' => $from,
                    'detail' => $exception->getMessage(),
                    'state' => $state,
                ]);

                $this->whatsAppClient->sendTextMessage(
                    $from,
                    "Me falta información para crear la cita:\n\n"
                    . $exception->getMessage()
                    . "\n\nEscribe *agenda* para iniciar de nuevo."
                );

                return;
            } catch (\Throwable $exception) {
                $this->logger->error('Error creando cita desde WhatsApp', [
                    'wa_id' => $from,
                    'detail' => $exception->getMessage(),
                    'state' => $state,
                    'trace' => $exception->getTraceAsString(),
                ]);

                $this->whatsAppClient->sendTextMessage(
                    $from,
                    "No pude crear la cita.\n\n"
                    . "Detalle: " . $exception->getMessage()
                    . "\n\nPuede que el horario se haya ocupado. Escribe *agenda* para elegir otro horario."
                );

                return;
            }
        }

        $this->whatsAppClient->sendTextMessage(
            $from,
            $this->formatBookingConfirmation($state)
        );
    }

    private function formatBookingConfirmation(array $state): string
    {
        $notes = trim((string) ($state['customer_notes'] ?? ''));

        return sprintf(
            "Confirma los datos de tu cita:\n\nSucursal: %s\nServicio: %s\nBarbero: %s\nFecha: %s\nHorario: %s\nNombre: %s\nCorreo: %s\nTeléfono: %s\nNotas: %s\n\nResponde *CONFIRMAR* para reservar o *CANCELAR* para salir.",
            (string) ($state['branch_name'] ?? ''),
            (string) ($state['service_name'] ?? ''),
            (string) ($state['barber_name'] ?? ''),
            (string) ($state['date'] ?? ''),
            (string) ($state['time_label'] ?? ''),
            (string) ($state['customer_name'] ?? ''),
            (string) ($state['customer_email'] ?? ''),
            (string) ($state['customer_phone'] ?? ''),
            $notes !== '' ? $notes : 'Sin notas'
        );
    }

    private function extractPhoneFromWhatsAppId(string $waId): string
    {
        $phone = preg_replace('/\D+/', '', $waId) ?? '';

        if (str_starts_with($phone, '521') && strlen($phone) === 13) {
            return substr($phone, 3);
        }

        if (str_starts_with($phone, '52') && strlen($phone) === 12) {
            return substr($phone, 2);
        }

        return strlen($phone) > 10 ? substr($phone, -10) : $phone;
    }

    private function extractBookingDateTextFromMessage(string $message): ?string
    {
        $text = $this->normalizeDateText($message);

        foreach ([
            '/\bhoy\b/u',
            '/\bmanana\b/u',
            '/\b\d{1,2}\/\d{1,2}\/\d{4}\b/u',
            '/\b\d{4}-\d{1,2}-\d{1,2}\b/u',
            '/(?:para\s+el|para|el)?\s*\d{1,2}\s*(?:de\s*)?(?:enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)\b/u',
            '/(?:para\s+el|para|el)\s+\d{1,2}\b/u',
        ] as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim((string) $matches[0]);
            }
        }

        return null;
    }

    private function formatDateHintForCustomer(string $date): string
    {
        try {
            $timezone = new \DateTimeZone('America/Mexico_City');
            $selectedDate = new \DateTimeImmutable($date . ' 00:00:00', $timezone);
            $today = new \DateTimeImmutable('today', $timezone);

            if ($selectedDate->format('Y-m-d') === $today->format('Y-m-d')) {
                return 'hoy';
            }

            if ($selectedDate->format('Y-m-d') === $today->modify('+1 day')->format('Y-m-d')) {
                return 'mañana';
            }

            return $selectedDate->format('d/m/Y');
        } catch (\Throwable) {
            return $date;
        }
    }

    private function parseBookingDate(string $message): ?string
    {
        $text = $this->normalizeDateText($message);
        $timezone = new \DateTimeZone('America/Mexico_City');
        $today = new \DateTimeImmutable('today', $timezone);

        if (preg_match('/\bhoy\b/u', $text)) {
            return $today->format('Y-m-d');
        }

        if (preg_match('/\bmanana\b/u', $text)) {
            return $today->modify('+1 day')->format('Y-m-d');
        }

        if (preg_match('/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/u', $text, $matches)) {
            return $this->buildBookingDate((int) $matches[3], (int) $matches[2], (int) $matches[1]);
        }

        if (preg_match('/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/u', $text, $matches)) {
            return $this->buildBookingDate((int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        $months = $this->spanishMonths();

        if (preg_match('/(?:para\s+el|para|el)?\s*(\d{1,2})\s*(?:de\s*)?([a-z]+)/u', $text, $matches)) {
            $day = (int) $matches[1];
            $monthName = $matches[2];

            if (isset($months[$monthName])) {
                $month = $months[$monthName];
                $year = (int) $today->format('Y');
                $candidate = $this->buildBookingDate($year, $month, $day);

                if ($candidate === null) {
                    return null;
                }

                if (new \DateTimeImmutable($candidate . ' 00:00:00', $timezone) < $today) {
                    $candidate = $this->buildBookingDate($year + 1, $month, $day);
                }

                return $candidate;
            }
        }

        if (preg_match('/(?:para\s+el|para|el)\s+(\d{1,2})\b/u', $text, $matches)) {
            return $this->buildFutureBookingDateFromDay((int) $matches[1], $today, $timezone);
        }

        if (preg_match('/^\s*(\d{1,2})\s*$/u', $text, $matches)) {
            return $this->buildFutureBookingDateFromDay((int) $matches[1], $today, $timezone);
        }

        return null;
    }

    private function buildFutureBookingDateFromDay(int $day, \DateTimeImmutable $today, \DateTimeZone $timezone): ?string
    {
        $candidate = $this->buildBookingDate((int) $today->format('Y'), (int) $today->format('m'), $day);

        if ($candidate === null) {
            return null;
        }

        if (new \DateTimeImmutable($candidate . ' 00:00:00', $timezone) < $today) {
            $nextMonth = $today->modify('first day of next month');
            $candidate = $this->buildBookingDate((int) $nextMonth->format('Y'), (int) $nextMonth->format('m'), $day);
        }

        return $candidate;
    }

    private function buildBookingDate(int $year, int $month, int $day): ?string
    {
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * @return array<string, int>
     */
    private function spanishMonths(): array
    {
        return [
            'enero' => 1,
            'febrero' => 2,
            'marzo' => 3,
            'abril' => 4,
            'mayo' => 5,
            'junio' => 6,
            'julio' => 7,
            'agosto' => 8,
            'septiembre' => 9,
            'setiembre' => 9,
            'octubre' => 10,
            'noviembre' => 11,
            'diciembre' => 12,
        ];
    }

    private function normalizeDateText(string $message): string
    {
        $text = mb_strtolower(trim($message));
        $text = strtr($text, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $text = preg_replace('/[^a-z0-9:\/\-\s#]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function isPastBookingDate(string $date): bool
    {
        try {
            $timezone = new \DateTimeZone('America/Mexico_City');
            $selectedDate = new \DateTimeImmutable($date . ' 00:00:00', $timezone);
            $today = new \DateTimeImmutable('today', $timezone);

            return $selectedDate < $today;
        } catch (\Throwable) {
            return true;
        }
    }

    private function shouldStartCancellationFlow(string $normalizedBody): bool
    {
        return in_array($normalizedBody, [
                'cancelar cita',
                'cancelar mi cita',
                'quiero cancelar',
                'quiero cancelar cita',
                'quiero cancelar mi cita',
                'quiero cancelar folio',
                'quiero cancelar el folio',
                'cancelar folio',
                'cancelar el folio',
                'cancelacion',
                'cancelación',
                'anular cita',
                'anular mi cita',
                'anular reservacion',
                'anular reservación',
            ], true)
            || str_contains($normalizedBody, 'cancelar cita')
            || str_contains($normalizedBody, 'cancelar mi cita')
            || str_contains($normalizedBody, 'cancelar folio')
            || str_contains($normalizedBody, 'cancelar el folio')
            || str_contains($normalizedBody, 'cancelación')
            || str_contains($normalizedBody, 'cancelacion')
            || str_contains($normalizedBody, 'anular cita')
            || str_contains($normalizedBody, 'anular mi cita')
            || str_contains($normalizedBody, 'anular reservacion')
            || str_contains($normalizedBody, 'anular reservación');
    }

    private function startCancellationFlow(string $from): void
    {
        $this->conversationStateService->start($from, 1);

        $this->conversationStateService->updateState(
            $from,
            'cancelling_waiting_folio',
            [
                'payload_json' => [
                    'flow' => 'appointment_cancellation',
                ],
            ]
        );

        $this->whatsAppClient->sendTextMessage(
            $from,
            "Claro. Para cancelar tu cita necesito el *folio*.\n\nEjemplo:\n*11*\n\nSi no tienes el folio, escribe *NO* para salir."
        );
    }

    private function handleCancellationFolio(string $from, string $normalizedBody): void
    {
        if (in_array($normalizedBody, ['no', 'salir', 'cancelar'], true)) {
            $this->conversationStateService->clear($from);

            $this->whatsAppClient->sendTextMessage(
                $from,
                "Listo, no cancelé ninguna cita.\n\nPuedes escribir *agenda* si quieres agendar una nueva cita."
            );

            return;
        }

        if (!ctype_digit($normalizedBody)) {
            $this->whatsAppClient->sendTextMessage(
                $from,
                "El folio debe ser numérico.\n\nEjemplo:\n*11*\n\nEscribe el folio o responde *NO* para salir."
            );

            return;
        }

        $appointmentId = (int) $normalizedBody;
        $appointment = $this->appointmentCancellationService->findCancelableAppointmentByFolio($appointmentId);

        if ($appointment === null) {
            $this->whatsAppClient->sendTextMessage(
                $from,
                "No encontré una cita futura activa con ese folio.\n\nRevisa el número e intenta de nuevo, o responde *NO* para salir."
            );

            return;
        }

        $this->conversationStateService->updateState(
            $from,
            'cancelling_confirming',
            [
                'payload_json' => [
                    'flow' => 'appointment_cancellation',
                    'appointment_id' => $appointmentId,
                    'appointment' => $appointment,
                ],
            ]
        );

        $this->whatsAppClient->sendTextMessage(
            $from,
            $this->appointmentCancellationService->formatAppointmentForConfirmation($appointment)
        );
    }

    private function handleCancellationConfirmation(string $from, string $normalizedBody, array $state): void
    {
        $payload = $this->decodeStatePayload($state);

        if (in_array($normalizedBody, ['no', 'salir', 'cancelar'], true)) {
            $this->conversationStateService->clear($from);

            $this->whatsAppClient->sendTextMessage(
                $from,
                "Listo, no cancelé ninguna cita.\n\nTu reservación sigue activa."
            );

            return;
        }

        if (!in_array($normalizedBody, ['cancelar cita', 'confirmar', 'si', 'sí', 'ok'], true)) {
            $appointment = $payload['appointment'] ?? null;

            if (is_array($appointment)) {
                $this->whatsAppClient->sendTextMessage(
                    $from,
                    $this->appointmentCancellationService->formatAppointmentForConfirmation($appointment)
                );

                return;
            }

            $this->whatsAppClient->sendTextMessage(
                $from,
                "Para confirmar la cancelación responde *CANCELAR CITA*.\n\nPara salir responde *NO*."
            );

            return;
        }

        $appointmentId = (int) ($payload['appointment_id'] ?? 0);

        if ($appointmentId <= 0) {
            $this->conversationStateService->clear($from);

            $this->whatsAppClient->sendTextMessage(
                $from,
                "Perdí el folio de la cita. No cancelé nada.\n\nEscribe *cancelar cita* para iniciar de nuevo."
            );

            return;
        }

        try {
            $appointment = $this->appointmentCancellationService->cancelByFolio($appointmentId);

            $this->conversationStateService->clear($from);

            $this->whatsAppClient->sendTextMessage(
                $from,
                sprintf(
                    "Tu cita fue cancelada correctamente.\n\nFolio: *%s*\nSucursal: *%s*\nServicio: *%s*\nFecha: *%s*\nHorario: *%s*",
                    (string) ($appointment['appointment_id'] ?? ''),
                    (string) ($appointment['branch_name'] ?? ''),
                    (string) ($appointment['service_name'] ?? ''),
                    (string) ($appointment['scheduled_date_display'] ?? ''),
                    (string) ($appointment['scheduled_time_label'] ?? '')
                )
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Error cancelando cita desde WhatsApp', [
                'wa_id' => $from,
                'appointment_id' => $appointmentId,
                'detail' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->conversationStateService->clear($from);

            $this->whatsAppClient->sendTextMessage(
                $from,
                "No pude cancelar la cita.\n\nDetalle: "
                . $exception->getMessage()
                . "\n\nPuedes intentar de nuevo escribiendo *cancelar cita*."
            );
        }
    }

    private function decodeStatePayload(array $state): array
    {
        $payload = $state['payload_json'] ?? null;

        if (is_array($payload)) {
            return $payload;
        }

        if (!is_string($payload) || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function startCancellationFlowAndReturnMessage(string $from): string
    {
        $this->conversationStateService->start($from, 1);

        $this->conversationStateService->updateState(
            $from,
            'cancelling_waiting_folio',
            [
                'payload_json' => [
                    'flow' => 'appointment_cancellation',
                ],
            ]
        );

        return "Claro. Para cancelar tu cita necesito el *folio*.\n\n"
            . "Ejemplo:\n*18*\n\n"
            . "Si no tienes el folio, escribe *NO* para salir.";
    }

    private function extractAppointmentFolioFromText(string $text): ?int
    {
        $normalizedText = mb_strtolower(trim($text));

        if (preg_match('/(?:folio|cita)\s*#?\s*(\d+)/i', $normalizedText, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/\b(\d{1,10})\b/', $normalizedText, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function startCancellationFlowWithFolio(string $from, int $appointmentId): void
    {
        $appointment = $this->appointmentCancellationService->findCancelableAppointmentByFolio($appointmentId);

        if ($appointment === null) {
            $this->conversationStateService->start($from, 1);

            $this->conversationStateService->updateState(
                $from,
                'cancelling_waiting_folio',
                [
                    'payload_json' => [
                        'flow' => 'appointment_cancellation',
                    ],
                ]
            );

            $this->whatsAppClient->sendTextMessage(
                $from,
                sprintf(
                    "No encontré una cita futura activa con el folio *%s*.\n\nRevisa el número e intenta de nuevo, o responde *NO* para salir.",
                    (string) $appointmentId
                )
            );

            return;
        }

        $this->conversationStateService->start($from, 1);

        $this->conversationStateService->updateState(
            $from,
            'cancelling_confirming',
            [
                'payload_json' => [
                    'flow' => 'appointment_cancellation',
                    'appointment_id' => $appointmentId,
                    'appointment' => $appointment,
                ],
            ]
        );

        $this->whatsAppClient->sendTextMessage(
            $from,
            $this->appointmentCancellationService->formatAppointmentForConfirmation($appointment)
        );
    }

    private function mainMenu(): string
    {
        return "Hola 👋 Soy el asistente de la barbería.\n\n"
            . "Puedo ayudarte con:\n"
            . "1. Agendar una cita\n"
            . "2. Ver servicios por sucursal\n"
            . "3. Ver barberos disponibles\n"
            . "4. Reagendar una cita\n"
            . "5. Recomendar productos\n"
            . "6. Recomendar un corte según tu tipo de cara\n\n"
            . "Escribe por ejemplo:\n"
            . "- agenda\n"
            . "- quiero una cita\n"
            . "- servicios\n"
            . "- barberos\n"
            . "- productos\n"
            . "- corte para cara redonda\n\n"
            . "Para consultar servicios o agendar, primero te pediré la sucursal.";
    }

    private function servicesResponse(): string
    {
        return "Para consultar servicios primero necesito saber la sucursal.\n\n"
            . "Escribe *servicios* o *agenda* para comenzar.";
    }

    private function agendaResponse(string $message): string
    {
        return "Claro. Para revisar disponibilidad vamos a iniciar tu cita paso a paso.\n\n"
            . "Escribe *agenda* para comenzar.";
    }

    private function barbersResponse(): string
    {
        return "Puedo ayudarte a consultar barberos disponibles.\n\n"
            . "Para hacerlo necesito primero conocer sucursal, servicio y fecha.\n\n"
            . "Escribe *agenda* para comenzar.";
    }

    private function rescheduleResponse(): string
    {
        return "Claro, puedo ayudarte a reagendar.\n\n"
            . "Para ubicar tu cita necesito:\n"
            . "1. Nombre con el que se registró\n"
            . "2. Día original de la cita\n"
            . "3. Nuevo día u horario deseado\n\n"
            . "Ejemplo:\n"
            . "Reagendar Ernesto, cita de hoy, para mañana a las 5";
    }

    private function productsResponse(string $message): string
    {
        $text = mb_strtolower($message);

        if (str_contains($text, 'barba')) {
            return "Para barba te recomiendo preguntar en sucursal por:\n\n"
                . "1. Aceite para barba\n"
                . "2. Bálsamo para barba\n"
                . "3. Shampoo para barba\n\n"
                . "Te ayudan a hidratar, dar forma y reducir resequedad.";
        }

        if (
            str_contains($text, 'fijacion')
            || str_contains($text, 'fijación')
            || str_contains($text, 'pomada')
            || str_contains($text, 'cera')
            || str_contains($text, 'gel')
        ) {
            return "Para peinado con fijación puedes buscar:\n\n"
                . "1. Pomada de fijación media\n"
                . "2. Cera con acabado natural\n"
                . "3. Gel de fijación fuerte\n\n"
                . "Si quieres acabado natural, pide cera o pomada mate.";
        }

        return "Puedo recomendarte productos según lo que necesitas:\n\n"
            . "1. Fijación fuerte\n"
            . "2. Acabado natural\n"
            . "3. Cuidado de barba\n"
            . "4. Cabello seco\n"
            . "5. Control de frizz\n\n"
            . "Ejemplo: producto para fijación fuerte";
    }

    private function haircutRecommendationResponse(string $message): string
    {
        $text = mb_strtolower($message);

        if (str_contains($text, 'redonda')) {
            return "Para cara redonda suelen favorecer cortes con volumen arriba y laterales más cortos:\n\n"
                . "1. Quiff texturizado\n"
                . "2. Pompadour bajo\n"
                . "3. Fade medio con volumen superior\n"
                . "4. Side part con laterales limpios\n\n"
                . "La idea es alargar visualmente el rostro.";
        }

        if (str_contains($text, 'ovalada')) {
            return "Para cara ovalada hay bastante libertad. Opciones recomendadas:\n\n"
                . "1. Crop texturizado\n"
                . "2. Fade bajo\n"
                . "3. Slick back\n"
                . "4. Corte clásico con raya lateral";
        }

        if (str_contains($text, 'cuadrada')) {
            return "Para cara cuadrada funcionan bien cortes que respetan la mandíbula marcada:\n\n"
                . "1. Buzz cut con fade\n"
                . "2. Crew cut\n"
                . "3. Side part clásico\n"
                . "4. French crop";
        }

        if (str_contains($text, 'alargada')) {
            return "Para cara alargada conviene evitar demasiado volumen arriba. Opciones recomendadas:\n\n"
                . "1. Corte medio texturizado\n"
                . "2. Flequillo ligero\n"
                . "3. Side part bajo\n"
                . "4. Taper clásico";
        }

        if (str_contains($text, 'diamante')) {
            return "Para cara tipo diamante conviene equilibrar pómulos y frente:\n\n"
                . "1. Fringe texturizado\n"
                . "2. Taper con volumen medio\n"
                . "3. Corte en capas\n"
                . "4. Side swept";
        }

        if (str_contains($text, 'corazon') || str_contains($text, 'corazón')) {
            return "Para cara tipo corazón conviene equilibrar frente y mentón:\n\n"
                . "1. Flequillo ligero\n"
                . "2. Taper bajo\n"
                . "3. Corte texturizado medio\n"
                . "4. Side part suave";
        }

        return "Claro. Te puedo recomendar cortes según tu tipo de cara.\n\n"
            . "Dime si tu cara es:\n"
            . "1. Ovalada\n"
            . "2. Redonda\n"
            . "3. Cuadrada\n"
            . "4. Alargada\n"
            . "5. Diamante\n"
            . "6. Corazón\n\n"
            . "Ejemplo: corte para cara redonda";
    }

    private function unknownResponse(): string
    {
        return "No estoy seguro de haber entendido tu mensaje.\n\n"
            . "Puedes escribir:\n"
            . "- agenda\n"
            . "- quiero una cita\n"
            . "- servicios\n"
            . "- barberos\n"
            . "- productos\n"
            . "- reagendar\n\n"
            . "Para consultar servicios o agendar, primero te pediré la sucursal.";
    }
}
