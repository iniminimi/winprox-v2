<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Marketing\CreatePromoCampaignAction;
use App\Actions\Marketing\DeletePromoCampaignAction;
use App\Actions\Marketing\PausePromoCampaignSendingAction;
use App\Actions\Marketing\QueuePromoCampaignEmailsAction;
use App\Actions\Marketing\ResumePromoCampaignSendingAction;
use App\Actions\Marketing\SortPromoCampaignsForPlatformListAction;
use App\Actions\Marketing\SummarizePromoCampaignsDeliveryAction;
use App\Actions\Marketing\UpdatePromoCampaignAction;
use App\Data\Marketing\UpdatePromoCampaignData;
use App\Enums\PromoEmailsPauseReason;
use App\Enums\PromoLanding;
use App\Http\Requests\Marketing\ApiQueuePromoCampaignEmailsRequest;
use App\Http\Requests\Marketing\ApiUpdatePromoCampaignRequest;
use App\Http\Requests\Marketing\CreatePromoCampaignRequest;
use App\Http\Resources\PromoCampaignResource;
use App\Models\PromoCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PromoCampaignController extends Controller
{
    public function index(
        SummarizePromoCampaignsDeliveryAction $summarize,
        SortPromoCampaignsForPlatformListAction $sort,
    ): JsonResponse {
        $this->authorize('viewAny', PromoCampaign::class);

        $campaigns = PromoCampaign::query()
            ->withCount('targets')
            ->latest('id')
            ->paginate(25);

        $summaries = $summarize->handle($campaigns->getCollection());
        $sorted = $sort->handle($campaigns->getCollection(), $summaries);
        $campaigns->setCollection($sorted);

        $campaigns->getCollection()->each(
            function (PromoCampaign $campaign) use ($summaries): void {
                $campaign->deliverySummary = $summaries[(int) $campaign->id] ?? null;
            },
        );

        return $this->paginated(PromoCampaignResource::collection($campaigns));
    }

    public function show(PromoCampaign $promoCampaign, SummarizePromoCampaignsDeliveryAction $summarize): JsonResponse
    {
        $this->authorize('view', $promoCampaign);

        $promoCampaign->loadCount('targets');
        $summaries = $summarize->handle(collect([$promoCampaign]));
        $promoCampaign->deliverySummary = $summaries[(int) $promoCampaign->id] ?? null;

        $resource = new PromoCampaignResource($promoCampaign);
        $resource->includeContent = true;

        return $this->item($resource);
    }

    public function store(Request $request, CreatePromoCampaignAction $create): JsonResponse
    {
        $this->authorize('create', PromoCampaign::class);

        $validated = $request->validate(CreatePromoCampaignRequest::ruleSet());
        $actorUserId = (int) $request->user()->id;

        $campaign = $create->handle(
            slug: $validated['slug'],
            name: $validated['name'],
            locale: $validated['locale'],
            actorUserId: $actorUserId,
            landing: PromoLanding::from($validated['landing']),
        );

        $resource = new PromoCampaignResource($campaign);
        $resource->includeContent = true;

        return $this->item($resource, 201);
    }

    public function update(
        Request $request,
        PromoCampaign $promoCampaign,
        UpdatePromoCampaignAction $update,
    ): JsonResponse {
        $this->authorize('update', $promoCampaign);

        $validated = $request->validate(ApiUpdatePromoCampaignRequest::ruleSet());
        $mapping = is_array($validated['column_mapping'] ?? null) ? $validated['column_mapping'] : [];

        try {
            $campaign = $update->handle(
                campaign: $promoCampaign,
                data: new UpdatePromoCampaignData(
                    name: $validated['name'],
                    locale: $validated['locale'],
                    landing: PromoLanding::from($validated['landing']),
                    letterBodyHtml: $validated['letter_body_html'] ?? null,
                    emailSubject: $validated['email_subject'] ?? null,
                    emailBodyHtml: $validated['email_body_html'] ?? null,
                    flowImagePath: $validated['flow_image_path'] ?? null,
                    youtubeUrl: $validated['youtube_url'] ?? null,
                    columnMapping: $mapping !== [] ? $mapping : null,
                ),
                actorUserId: (int) $request->user()->id,
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $resource = new PromoCampaignResource($campaign);
        $resource->includeContent = true;

        return $this->item($resource);
    }

    public function destroy(Request $request, PromoCampaign $promoCampaign, DeletePromoCampaignAction $delete): JsonResponse
    {
        $this->authorize('delete', $promoCampaign);

        $delete->handle($promoCampaign, (int) $request->user()->id);

        return response()->json(null, 204);
    }

    public function queueEmails(
        Request $request,
        PromoCampaign $promoCampaign,
        QueuePromoCampaignEmailsAction $queue,
    ): JsonResponse {
        $this->authorize('update', $promoCampaign);

        $validated = $request->validate(ApiQueuePromoCampaignEmailsRequest::ruleSet());

        try {
            $result = $queue->handle(
                campaign: $promoCampaign,
                actorUserId: (int) $request->user()->id,
                delaySeconds: (int) ($validated['delay_seconds'] ?? 20),
                forceResend: (bool) ($validated['force_resend'] ?? false),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return $this->success($result);
    }

    public function pauseSending(
        Request $request,
        PromoCampaign $promoCampaign,
        PausePromoCampaignSendingAction $pause,
    ): JsonResponse {
        $this->authorize('update', $promoCampaign);

        $result = $pause->handle(
            $promoCampaign,
            (int) $request->user()->id,
            PromoEmailsPauseReason::Manual,
        );

        return $this->success($result);
    }

    public function resumeSending(
        Request $request,
        PromoCampaign $promoCampaign,
        ResumePromoCampaignSendingAction $resume,
    ): JsonResponse {
        $this->authorize('update', $promoCampaign);

        $result = $resume->handle($promoCampaign, (int) $request->user()->id);

        return $this->success($result);
    }
}
