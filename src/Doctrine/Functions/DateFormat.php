<?php

namespace App\Doctrine\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType; // Importante: Nueva interfaz para constantes

class DateFormat extends FunctionNode
{
    private ?Node $dateExpression = null;
    private ?Node $formatExpression = null;

    public function parse(Parser $parser): void
    {
        // En Doctrine 3, usamos TokenType en lugar de Lexer para las constantes
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->dateExpression = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->formatExpression = $parser->StringPrimary();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return 'DATE_FORMAT(' .
            $this->dateExpression->dispatch($sqlWalker) . ', ' .
            $this->formatExpression->dispatch($sqlWalker) .
            ')';
    }
}
