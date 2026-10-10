<?php

namespace App\Http\Requests\Admin\SystemSettings;

use App\Services\SystemSettings\SystemSettingsSchema;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base request for a settings group. Rules are built from the schema, so the
 * browser can only ever validate/write keys declared for that group.
 */
abstract class UpdateSystemSettingsRequest extends FormRequest
{
    abstract protected function settingsGroup(): string;

    public function authorize(): bool
    {
        // Authorization is enforced by the route permission middleware and
        // the SystemSettingPolicy; validation lives here.
        return true;
    }

    public function rules(): array
    {
        return app(SystemSettingsSchema::class)->rulesForGroup($this->settingsGroup());
    }

    /**
     * Settings keys contain dots (e.g. "platform.name"). Laravel's validator
     * nests dotted attribute names into multi-dimensional arrays, which would
     * silently drop them from validated() output. We return the declared keys
     * verbatim from the raw input instead, so the service receives a flat map.
     *
     * @return array<string,mixed>
     */
    public function validated($key = null, $default = null)
    {
        $input = $this->all();
        $validated = [];

        foreach (array_keys($this->rules()) as $attribute) {
            if (array_key_exists($attribute, $input)) {
                $validated[$attribute] = $input[$attribute];
            }
        }

        if ($key !== null) {
            return data_get($validated, $key, $default);
        }

        return $validated;
    }
}
