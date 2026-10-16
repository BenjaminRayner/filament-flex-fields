<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Authoring;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ArtifactPath;
use InvalidArgumentException;

/** Bounded identifiers are substituted into source; arbitrary code is never accepted. */
final readonly class ComponentPackageBlueprint
{
    public function __construct(
        public string $package,
        public string $prefix,
        public string $phpNamespace,
        public string $className,
    ) {
        if (strlen($package) > 128 || preg_match('~^[a-z][a-z0-9]*(?:-[a-z0-9]+)*/[a-z][a-z0-9]*(?:-[a-z0-9]+)*$~D', $package) !== 1
            || strlen($prefix) > 48 || preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $prefix) !== 1
            || strlen($phpNamespace) > 192 || preg_match('/^[A-Z][A-Za-z0-9]*(?:\\\\[A-Z][A-Za-z0-9]*)+$/D', $phpNamespace) !== 1
            || strlen($className) > 64 || preg_match('/^[A-Z][A-Za-z0-9]*$/D', $className) !== 1) {
            throw new InvalidArgumentException('Use a lowercase vendor/package and asset prefix, a Vendor\\Package namespace and an alphanumeric class name.');
        }
        ArtifactPath::assert($package);
        foreach ([...explode('\\', $phpNamespace), $className] as $identifier) {
            if (in_array(strtolower($identifier), ['abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'enum', 'eval', 'exit', 'extends', 'final', 'finally', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'include_once', 'instanceof', 'insteadof', 'interface', 'isset', 'list', 'match', 'namespace', 'new', 'or', 'print', 'private', 'protected', 'public', 'readonly', 'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var', 'while', 'xor', 'yield', 'parent', 'self', 'true', 'false', 'null', 'string', 'int', 'float', 'bool', 'object', 'mixed', 'void', 'never', 'iterable', 'resource'], true)) {
                throw new InvalidArgumentException('PHP namespace/class cannot contain a reserved identifier.');
            }
        }
    }

    /** @return array<string, string> */
    public function substitutions(): array
    {
        return ['{{PACKAGE}}' => $this->package, '{{PREFIX}}' => $this->prefix,
            '{{NAMESPACE}}' => $this->phpNamespace, '{{JSON_NAMESPACE}}' => str_replace('\\', '\\\\', $this->phpNamespace),
            '{{CLASS}}' => $this->className, '{{FACTORY}}' => lcfirst($this->className).'FieldComponent'];
    }
}
