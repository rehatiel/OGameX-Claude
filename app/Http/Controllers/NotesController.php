<?php

namespace OGame\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use OGame\Http\Requests\Notes\AjaxCreateNoteRequest;
use OGame\Services\NoteService;

class NotesController extends OGameController
{
    /**
     * Shows the notes popup page
     *
     * @param Request $request
     * @param NoteService $noteService
     * @return View
     */
    public function overlay(Request $request, NoteService $noteService): View
    {
        $data = [];

        $notesDeleted = false;

        if ($request->isMethod('post')) {
            $deleteMethod = $request->input('noticeDeleteMethode');

            if ($deleteMethod === "1") {
                $noteIds = $request->input('delIds', []);
                if (!empty($noteIds)) {
                    $noteService->deleteMarkedNotes($noteIds);
                    $notesDeleted = true;
                }
            } elseif ($deleteMethod === "2") {
                $noteService->deleteAllNotesForUser();
                $notesDeleted = true;
            }

            if ($notesDeleted) {
                $data['success'] = __('Note(s) has(ve) been deleted');
            }
        }

        $data['notes'] = $noteService->getAllNotesForUser();
        return view('ingame.notes.overlay', $data);
    }

    /**
     * Shows the notes view popup page
     *
     * @param Request $request
     * @param NoteService $noteService
     * @return View
     */
    public function view(Request $request, NoteService $noteService): View
    {
        $note = null;

        $noteId = $request->input('id');
        if ($noteId) {
            $note = $noteService->getNoteById($noteId);
        }

        return view('ingame.notes.create')->with([
            'noteId' => $note ? $note->id : 0,
            'priority' => $note ? $note->priority : 2,
            'subject' => $note ? $note->subject : '',
            'content' => $note ? $note->content : '',
        ]);
    }

    /**
     * Create a new note
     *
     * @param AjaxCreateNoteRequest $request
     * @param NoteService $noteService
     * @return JsonResponse
     */
    public function ajaxCreate(AjaxCreateNoteRequest $request, NoteService $noteService): JsonResponse
    {
        $validated = $request->validated();

        $data = [
            'priority' => $validated['noticePrio'] ?? 2,
            'subject' => $validated['noticeSubject'] ?? __('Your subject'),
            'content' => $validated['noticeText'],
        ];

        try {
            if (!empty($validated['id'])) {
                // Update existing note
                $note = $noteService->updateNoteForUser($validated['id'], $data);
                $message = __('Note edited');
            } else {
                // Create new note
                $note = $noteService->createNoteForUser($data);
                $message = __('Note has been added');
            }

            return response()->json([
                'id' => $note->id,
                'error' => null,
                'success' =>  $message
            ]);
        } catch (Exception $e) {
            return response()->json([
                'id' => null,
                'error' => __('Failed to process note'),
                'success' => null
            ], 500);
        }
    }
}
