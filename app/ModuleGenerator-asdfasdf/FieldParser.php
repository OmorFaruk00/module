<?php

namespace App\ModuleGenerator;

class FieldParser
{
    public function parse(?string $fields): array
    {
        if (!$fields) {
            return [];
        }

        return collect(explode(',', $fields))
            ->map(function ($field) {
                $parts = explode(':', trim($field));

                return [
                    'name' => $parts[0],
                    'type' => $parts[1] ?? 'string',
                    'modifiers' => array_slice($parts, 2),
                ];
            })
            ->values()
            ->toArray();
    }

    public function migration(array $field): string
    {
        $name = $field['name'];
        $type = $field['type'];
        $modifiers = $field['modifiers'];

        if ($type === 'foreign') {
            $table = $modifiers[0] ?? str($name)
                ->replace('_id', '')
                ->plural();

            $column = "\$table->foreignId('{$name}')->constrained('{$table}')";

            return $this->applyModifiers($column, $modifiers, true) . ';';
        }

        $column = "\$table->{$type}('{$name}')";

        return $this->applyModifiers($column, $modifiers) . ';';
    }

    protected function applyModifiers(
        string $column,
        array $modifiers,
        bool $foreign = false
    ): string {
        foreach ($modifiers as $modifier) {

            if ($foreign && !in_array($modifier, ['nullable', 'index'])) {
                continue;
            }

            if ($modifier === 'nullable') {
                $column .= '->nullable()';
            }

            if ($modifier === 'unique') {
                $column .= '->unique()';
            }

            if ($modifier === 'index') {
                $column .= '->index()';
            }

            if (str_starts_with($modifier, 'default=')) {
                $value = substr($modifier, 8);

                if ($value === 'true' || $value === 'false') {
                    $value = $value === 'true' ? 'true' : 'false';
                } else {
                    $value = "'{$value}'";
                }

                $column .= "->default({$value})";
            }

            if (is_numeric($modifier) && $this->isLengthType($column)) {
                $column .= "({$modifier})";
            }
        }

        return $column;
    }

    protected function isLengthType(string $column): bool
    {
        return str_contains($column, 'string');
    }

    public function fillable(array $fields): string
    {
        return collect($fields)
            ->map(fn ($field) => "        '{$field['name']}',")
            ->implode("\n");
    }

    public function casts(array $fields): string
    {
        return collect($fields)
            ->filter(fn ($field) => in_array($field['type'], [
                'boolean',
                'integer',
                'decimal',
            ]))
            ->map(function ($field) {
                $cast = match ($field['type']) {
                    'boolean' => 'boolean',
                    'integer' => 'integer',
                    'decimal' => 'decimal:2',
                    default => 'string',
                };

                return "        '{$field['name']}' => '{$cast}',";
            })
            ->implode("\n");
    }
}
