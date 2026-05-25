<?php

namespace OGame\Http\Requests\Notes;

use Illuminate\Foundation\Http\FormRequest;
use OGame\Services\NoteService;

class AjaxCreateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $noteService = resolve(NoteService::class);

        return [
            'id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($noteService) {
                    if (!empty($value) && !$noteService->noteExistsAndBelongsToUser($value)) {
                        $fail('The selected note ID does not exist.');
                    }
                },
            ],
            'noticePrio'    => 'nullable|integer|min:1|max:3',
            'noticeSubject' => 'nullable|string|max:32',
            'noticeText'    => 'nullable|string|max:5000',
        ];
    }
}
