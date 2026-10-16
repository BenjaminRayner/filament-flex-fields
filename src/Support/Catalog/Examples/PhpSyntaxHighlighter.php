<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog\Examples;

/** PHP's tokenizer parses source without evaluating or importing the example. */
final class PhpSyntaxHighlighter
{
    /** @return list<string> Escaped HTML, one entry per source line. */
    public function lines(string $source): array
    {
        $lines = [''];
        foreach (token_get_all($source) as $token) {
            $text = is_array($token) ? $token[1] : $token;
            $type = is_array($token) ? $this->category($token[0]) : null;
            foreach (explode("\n", $text) as $index => $part) {
                if ($index > 0) {
                    $lines[] = '';
                }
                $escaped = htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $lines[array_key_last($lines)] .= $type === null || $part === '' ? $escaped : '<span class="fff-code-preview__token--'.$type.'">'.$escaped.'</span>';
            }
        }

        return $lines;
    }

    private function category(int $id): ?string
    {
        return match ($id) {
            T_COMMENT, T_DOC_COMMENT => 'comment',
            T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE => 'string',
            T_VARIABLE => 'variable',
            T_LNUMBER, T_DNUMBER => 'number',
            T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE => 'symbol',
            T_OPEN_TAG, T_CLOSE_TAG, T_USE, T_NAMESPACE, T_AS, T_NEW, T_FUNCTION, T_FN,
            T_RETURN, T_CLASS, T_STATIC, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_IF, T_ELSE,
            T_ELSEIF, T_FOREACH, T_FOR, T_WHILE, T_MATCH, T_THROW, T_TRY, T_CATCH,
            T_EXTENDS, T_IMPLEMENTS, T_ARRAY, T_CONST, T_DECLARE, T_BOOLEAN_AND,
            T_BOOLEAN_OR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_ARROW => 'keyword',
            default => null,
        };
    }
}
