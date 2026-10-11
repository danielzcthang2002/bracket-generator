<?php

namespace App\Http\Requests\Tournament;

use Illuminate\Foundation\Http\FormRequest;

class TournamentStoreRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'mode_type' => 'required|in:single_elimination,double_elimination,round_robin,swiss,free_for_all',
            'description' => 'nullable|string|max:1000',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after:start_at',
            'require_check_in' => 'boolean',
            'check_in_time' => 'nullable|integer|min:1',
            'max_entry' => 'nullable|integer|min:2',
            'split_participant' => 'nullable|boolean',
            'ffa_heat_size' => 'required_if:mode_type,free_for_all|nullable|integer|min:2',
            'head_to_head_count' => 'required_if:mode_type,round_robin|nullable|integer|min:1',
            'rank_by' => 'nullable|string|max:50',

            'points_per_match_win' => 'required_if:mode_type,swiss|nullable|numeric|min:0',
            'points_per_match_tie' => 'required_if:mode_type,swiss|nullable|numeric|min:0',
            'points_per_set_win' => 'required_if:mode_type,swiss|nullable|numeric|min:0',
            'points_per_set_tie' => 'required_if:mode_type,swiss|nullable|numeric|min:0',
            'points_per_bye' => 'required_if:mode_type,swiss|nullable|numeric|min:0',
            'ffa_advance_count' => 'required_if:mode_type,free_for_all|nullable|integer|min:1|lt:ffa_heat_size',
            'swiss_rounds' => 'required_if:mode_type,swiss|nullable|integer|min:1',
        ];
    }
}
