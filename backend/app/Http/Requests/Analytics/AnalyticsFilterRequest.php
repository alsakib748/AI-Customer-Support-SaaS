<?php

namespace App\Http\Requests\Analytics;

use App\Support\Analytics\AnalyticsInterval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyticsFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Period filters
            'period' => [
                'nullable',
                'string',
                Rule::in([
                    'today',
                    'yesterday',
                    '7d',
                    '30d',
                    '90d',
                    'this_month',
                    'last_month',
                    'this_year',
                ])
            ],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],

            // Interval
            'interval' => [
                'nullable',
                'string',
                Rule::in([
                    AnalyticsInterval::HOUR,
                    AnalyticsInterval::DAY,
                    AnalyticsInterval::WEEK,
                    AnalyticsInterval::MONTH,
                ])
            ],

            // Dimension filters
            'channel' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'max:30'],
            'priority' => ['nullable', 'string', 'max:20'],
            'agent_id' => ['nullable', 'integer', 'min:1'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'widget_id' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'provider' => ['nullable', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:100'],

            // Comparison
            'compare' => ['nullable', 'boolean'],

            // Grouping
            'group_by' => [
                'nullable',
                'string',
                Rule::in([
                    'day',
                    'week',
                    'month',
                    'agent',
                    'channel',
                    'status',
                    'priority',
                    'widget',
                    'provider',
                    'model',
                ])
            ],

            // Pagination for tables
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],

            // Sorting (whitelisted in services)
            'sort' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Ensure boolean coercion
        if ($this->has('compare')) {
            $this->merge([
                'compare' => filter_var($this->input('compare'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

}