<?php

namespace App\Actions\TenantPurge;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\ClockPoint;
use App\Models\Document;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use App\Models\InternalTeam;
use App\Models\IotEvent;
use App\Models\IotGateway;
use App\Models\IotRule;
use App\Models\IotSensor;
use App\Models\Issue;
use App\Models\IssuePhoto;
use App\Models\IssueUpdate;
use App\Models\Location;
use App\Models\QrCode;
use App\Models\Reservation;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Models\Worker;
use App\Models\WorkShift;
use Illuminate\Support\Facades\DB;

/**
 * Telt tenant-records vóór wissen (voor resultaatmail / audit).
 */
final class CollectTenantPurgeCountsAction
{
    /**
     * @return array<string, int>
     */
    public function handle(Tenant $tenant): array
    {
        $id = (int) $tenant->id;

        return [
            'users' => User::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'locations' => Location::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'units' => Unit::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'issues' => Issue::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'issue_updates' => IssueUpdate::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'issue_photos' => IssuePhoto::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'tasks' => Task::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'workers' => Worker::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'teams' => InternalTeam::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'categories' => Category::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'documents' => Document::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'announcements' => Announcement::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'qr_codes' => QrCode::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'reservations' => Reservation::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'webhooks' => WebhookEndpoint::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'work_shifts' => WorkShift::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'clock_points' => ClockPoint::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'esg_indicators' => EsgIndicator::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'esg_measurements' => EsgMeasurement::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'iot_gateways' => IotGateway::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'iot_sensors' => IotSensor::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'iot_events' => IotEvent::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'iot_rules' => IotRule::query()->withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'audit_logs' => (int) DB::table('audit_logs')->where('tenant_id', $id)->count(),
        ];
    }
}
