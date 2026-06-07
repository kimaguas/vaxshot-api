<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmailTemplateRequest;
use App\Http\Resources\EmailTemplateResource;
use App\Models\EmailTemplate;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    use LogsActivity;

    public function index()
    {
        $templates = EmailTemplate::orderBy('category')->orderBy('name')->get();

        return response()->json([
            'templates' => EmailTemplateResource::collection($templates),
        ]);
    }

    public function store(StoreEmailTemplateRequest $request)
    {
        if ($request->boolean('is_default')) {
            EmailTemplate::where('is_default', true)->update(['is_default' => false]);
        }

        $template = EmailTemplate::create($request->validated());

        $this->logActivity(
            action:      'CREATE',
            module:      'Email Templates',
            description: "Created email template: {$template->name}",
            newData:     $template->toArray()
        );

        return response()->json([
            'message'  => 'Email template created successfully',
            'template' => new EmailTemplateResource($template),
        ], 201);
    }

    public function update(StoreEmailTemplateRequest $request, EmailTemplate $template)
    {
        if ($request->boolean('is_default')) {
            EmailTemplate::where('is_default', true)
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        $old = $template->toArray();
        $template->update($request->validated());

        $this->logActivity(
            action:      'UPDATE',
            module:      'Email Templates',
            description: "Updated email template: {$template->name}",
            oldData:     $old,
            newData:     $template->fresh()->toArray()
        );

        return response()->json([
            'message'  => 'Email template updated successfully',
            'template' => new EmailTemplateResource($template),
        ]);
    }

    public function destroy(EmailTemplate $template)
    {
        $this->logActivity(
            action:      'DELETE',
            module:      'Email Templates',
            description: "Deleted email template: {$template->name}",
            oldData:     $template->toArray()
        );

        $template->delete();

        return response()->json(['message' => 'Email template deleted successfully']);
    }
}
