<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ModuleRegistry
{
    public function all(): array
    {
        return config('modules', []);
    }

    public function get(string $type): array
    {
        $definition = $this->all()[$type] ?? null;
        if (! $definition) {
            throw ValidationException::withMessages(['module_type' => 'The selected module type is not supported.']);
        }

        return $definition;
    }

    public function defaults(string $type): array
    {
        $definition = $this->get($type);
        $content = [];
        foreach ($definition['fields'] ?? [] as $field) {
            $content[$field['key']] = $field['default'] ?? ($field['type'] === 'checkbox' ? false : null);
        }
        foreach ($definition['repeaters'] ?? [] as $repeater) {
            $content[$repeater['key']] = [];
            for ($i = 0; $i < ($repeater['min'] ?? 0); $i++) {
                $row = [];
                foreach ($repeater['fields'] as $field) {
                    $row[$field['key']] = $field['default'] ?? ($field['type'] === 'checkbox' ? false : null);
                }
                $content[$repeater['key']][] = $row;
            }
        }

        return $content;
    }

    public function rules(string $type): array
    {
        $definition = $this->get($type);
        $rules = ['content' => ['required', 'array']];
        foreach ($definition['fields'] ?? [] as $field) {
            $rules['content.'.$field['key']] = $this->fieldRules($field);
        }
        foreach ($definition['repeaters'] ?? [] as $repeater) {
            $rules['content.'.$repeater['key']] = ['array', 'min:'.($repeater['min'] ?? 0), 'max:'.$repeater['max']];
            foreach ($repeater['fields'] as $field) {
                $rules['content.'.$repeater['key'].'.*.'.$field['key']] = $this->fieldRules($field);
            }
        }

        return $rules;
    }

    private function fieldRules(array $field): array
    {
        // Draft modules autosave before every required field has been completed.
        // Required-state validation is enforced by the builder/export validator.
        $rules = ['nullable'];
        $rules[] = $field['type'] === 'checkbox' ? 'boolean' : ($field['type'] === 'image' ? 'integer' : 'string');
        if (in_array($field['type'], ['text', 'richtext'], true)) {
            $rules[] = 'max:10000';
        }

        return $rules;
    }
}
