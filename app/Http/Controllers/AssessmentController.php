<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Assessment;
use App\Models\Question;
use App\Models\Option;


class AssessmentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', Rule::in(Assessment::NAMES)],
            'description' => 'required|string',
        ]);

        $validated['is_active'] = true;
        $validated['date_created'] = now();

        $assessment = Assessment::create($validated);

        return redirect()->route('admin.questions', $assessment->id)
            ->with('success', 'Asesmen berhasil dibuat, silakan tambahkan pertanyaan!');
    }

    public function update(Request $request, Assessment $assessment)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $assessment->update($validated);

        return redirect()->route('admin.assessments')
            ->with('success', 'Status asesmen berhasil diperbarui!');
    }

    public function storeQuestion(Request $request)
    {
        $validated = $request->validate([
            'assessment_id' => 'required|exists:assessments,id',
            'question_text' => 'required|string',
            'options' => 'required|array',
            'options.*.option_text' => 'required|string',
            'options.*.score' => 'required|integer|min:1|max:100',
        ]);

        $question = Question::create([
            'assessment_id' => $validated['assessment_id'],
            'question_text' => $validated['question_text'],
        ]);

        foreach ($validated['options'] as $option) {
            Option::create([
                'question_id' => $question->id,
                'option_text' => $option['option_text'],
                'score' => $option['score'],
            ]);
        }

        return redirect()->route('admin.questions', $validated['assessment_id'])
            ->with('success', 'Pertanyaan berhasil ditambahkan!');
    }

    public function multiStore(Request $request)
{
    $validated = $request->validate([
        'assessment_id' => 'required|exists:assessments,id',
        'questions' => 'required|array|min:1',
        'questions.*.question_text' => 'required|string',
        'questions.*.options' => 'required|array|min:1',
        'questions.*.options.*.option_text' => 'required|string',
        'questions.*.options.*.score' => 'required|integer|min:1|max:100',
    ]);

    foreach ($validated['questions'] as $questionData) {
        $question = Question::create([
            'assessment_id' => $validated['assessment_id'],
            'question_text' => $questionData['question_text'],
        ]);

        foreach ($questionData['options'] as $option) {
            Option::create([
                'question_id' => $question->id,
                'option_text' => $option['option_text'],
                'score' => $option['score'],
            ]);
        }
    }

    return redirect()->route('admin.dashboard')->with('success', 'Semua pertanyaan berhasil ditambahkan!');
}

    public function updateQuestion(Request $request, Question $question)
    {
        $validated = $request->validate([
            'question_text' => 'required|string',
            'options' => 'required|array|min:2',
            'options.*.id' => 'sometimes|exists:options,id',
            'options.*.option_text' => 'required|string',
            'options.*.score' => 'required|integer',
        ]);

        $question->update([
            'question_text' => $validated['question_text'],
        ]);

        $existingOptionIds = [];

        foreach ($validated['options'] as $optionData) {
            if (isset($optionData['id'])) {

                $option = Option::find($optionData['id']);
                $option->update([
                    'option_text' => $optionData['option_text'],
                    'score' => $optionData['score'],
                ]);
                $existingOptionIds[] = $option->id;
            } else {

                $option = Option::create([
                    'question_id' => $question->id,
                    'option_text' => $optionData['option_text'],
                    'score' => $optionData['score'],
                ]);
                $existingOptionIds[] = $option->id;
            }
        }


        Option::where('question_id', $question->id)
            ->whereNotIn('id', $existingOptionIds)
            ->delete();

        return redirect()->route('admin.questions', $question->assessment_id)
            ->with('success', 'Pertanyaan berhasil diperbarui!');
    }

    public function destroyQuestion(Question $question)
    {
        $assessmentId = $question->assessment_id;


        $question->options()->delete();


        $question->delete();

        return redirect()->route('admin.questions', $assessmentId)
            ->with('success', 'Pertanyaan berhasil dihapus!');
    }
}
