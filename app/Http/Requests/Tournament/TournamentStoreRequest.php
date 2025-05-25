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
            'check_in_time' => 'nullable|date|after_or_equal:start_at|before:end_at',
            'max_entry' => 'nullable|integer|min:1',
            'split_participant' => 'nullable|boolean',
            'participants_per_match' => 'nullable|integer|min:2',
            'head_to_head_count' => 'required_if:mode_type,round_robin|integer|min:1',
            'rank_by' => 'nullable|string|max:50',

            'points_per_match_win' => 'required_if:mode_type,swiss|numeric|min:0',
            'points_per_match_tie' => 'required_if:mode_type,swiss|numeric|min:0',
            'points_per_set_win' => 'required_if:mode_type,swiss|numeric|min:0',
            'points_per_set_tie' => 'required_if:mode_type,swiss|numeric|min:0',
            'points_per_bye' => 'required_if:mode_type,swiss|numeric|min:0',
        ];
    }
}
