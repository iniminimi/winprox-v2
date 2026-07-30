<h2>1. Who we are</h2>
<p>
    WinProx (“Work in Proximity”) is a SaaS platform for technical and operational site management:
    QR issue reporting and task follow-up for internal operational teams, and optional
    ESG/compliance recording and IoT Connect (sensor events into workflow).
</p>
<p>
    The platform is operated by:
</p>

@include('partials.wp-legal-operator')

<h2>2. Roles under the GDPR (EU and Belgium)</h2>
<p>The following roles apply within the platform:</p>
<ul>
    <li>The customer / administrator is the data controller.</li>
    <li>WinProx is the data processor.</li>
</ul>
<p>This means:</p>
<ul>
    <li>The customer decides which personal data is processed and for what purpose.</li>
    <li>WinProx processes personal data solely on the customer’s instructions.</li>
</ul>

<h2>3. Data we process</h2>

<p><strong>Users</strong></p>
<ul>
    <li>name</li>
    <li>email address</li>
    <li>role within the organisation</li>
    <li>language preference where applicable</li>
</ul>

<p><strong>Subscription and billing</strong></p>
<ul>
    <li>selected subscription plan (where applicable)</li>
    <li>end date of the trial period and paid subscription</li>
    <li>billing and payment data entered by you or your organisation, or processed via a payment provider</li>
</ul>

<p><strong>Locations and units</strong></p>
<ul>
    <li>locations (sites) and units within your organisation</li>
    <li>addresses and location data you enter</li>
</ul>

<p><strong>Issues and tasks</strong></p>
<ul>
    <li>issues and tasks</li>
    <li>descriptions, statuses and follow-up</li>
    <li>communication and history within the platform</li>
    <li>photos and attachments added to issues or tasks</li>
</ul>

<p><strong>Workers (without login)</strong></p>
<ul>
    <li>name or display name</li>
    <li>contact details (such as email address), where entered by the customer</li>
    <li>assignment to tasks within internal teams</li>
</ul>
<p>
    This data is managed by the customer / administrator. WinProx has no substantive control over what the customer enters.
</p>

<p><strong>QR reports</strong></p>
<ul>
    <li>data voluntarily submitted via a public QR portal (such as name, email address or description)</li>
    <li>technical metadata required for security and abuse prevention</li>
</ul>

<p><strong>ESG & Compliance (optional module)</strong></p>
<p>
    If the customer enables the optional ESG module, measurement values and compliance data may be recorded,
    for example during recurring inspections, when completing tasks on the QR portal, via the API, or — if
    IoT Connect is enabled — from sensor events.
</p>
<ul>
    <li>indicator definitions (name, type, unit, thresholds, options), including any translations of indicator texts.</li>
    <li>measurement values (such as number, yes/no, choice or text) with timestamp.</li>
    <li>link to issue, task, location, unit and optionally the worker; on the sensor path a task may be absent.</li>
    <li>corrections as new measurement rows (append-only); earlier values are retained.</li>
    <li>threshold alarms and resulting follow-up tasks when a measurement falls outside configured limits.</li>
    <li>API creation of measurements and optional webhooks (e.g. when a new measurement row is recorded), if the customer connects them.</li>
</ul>
<p>
    The module is optional and visible to administrators only when enabled. The customer is responsible
    for the content and use of ESG data within their organisation.
</p>

<p><strong>IoT Connect (optional module)</strong></p>
<p>
    If the customer enables IoT Connect, gateways may send events to WinProx. WinProx is not an IoT cloud or
    time-series platform: the customer (or their hardware partner) manages gateways and sensors; WinProx turns
    inbound events into workflow within the tenant.
</p>
<ul>
    <li>gateway configuration and authentication tokens (tokens are stored securely; a new token is typically shown once).</li>
    <li>sensor mappings (external id → location/unit, optionally an ESG indicator).</li>
    <li>alarm rules (thresholds, operator, assigned team, priority, text).</li>
    <li>event records (processed / ignored / deduplicated / failed) — no continuous time-series storage.</li>
    <li>on alarm: an approved issue and task within the organisation (with deduplication while an open task exists for the same rule).</li>
    <li>on measurement (Corporate, with ESG module): an ESG measurement row based on the sensor event.</li>
</ul>
<p>
    Personal data in IoT flows is limited to what the customer configures (e.g. assignment to teams/workers
    via issues and tasks). The customer remains responsible for sensor sources and event content.
</p>

<h2>4. AI Translations</h2>
<p>The platform uses AI translations for multilingual display:</p>
<ul>
    <li>translation of texts shown multilingually in the platform or QR portal (including issues, tasks, units, announcements, document descriptions, locations, categories, team names and ESG indicator texts); texts are queued for translation after approval.</li>
    <li>using a local Ollama instance (no external AI services / cloud).</li>
    <li>WinProx runs these translations periodically (typically daily), with no guaranteed turnaround time.</li>
    <li>translations are stored and retained according to the retention policy; organisation administrators can manually correct or complete translations in the platform.</li>
    <li>there is no per-organisation on/off switch; WinProx may pause the translation pipeline at platform level.</li>
</ul>

<h2>5. Processing purposes</h2>
<p>Data is processed for:</p>
<ul>
    <li>operating the platform.</li>
    <li>registering and following up issues and tasks.</li>
    <li>assigning work to internal teams and workers.</li>
    <li>QR reporting and communication between users within your organisation.</li>
    <li>sending email notifications on the customer’s instructions.</li>
    <li>product improvement through superuser onboarding statistics (aggregated where possible).</li>
    <li>security and logging.</li>
    <li>multilingual support via AI translations (run periodically by WinProx, with no guaranteed turnaround).</li>
    <li>recording and follow-up of ESG/compliance measurements (if the module is enabled).</li>
    <li>processing IoT events into issues, tasks and/or ESG measurements (if IoT Connect is enabled).</li>
</ul>

<h2>6. QR reporting and team access</h2>
<p>
    QR codes allow reporters to submit issues without an account. The customer / administrator decides which locations
    and units are available and which data is requested.
</p>
<p>
    Logged-in users and internal teams have access according to permissions set by the customer. WinProx processes
    personal data in this context solely as a technical processor acting on the customer’s instructions.
</p>

<h2>7. Support and access</h2>
<p>
    For technical support, WinProx may in exceptional cases access data via a support mode for superuser or support staff:
</p>
<ul>
    <li>solely for technical support and troubleshooting.</li>
    <li>read-only access by default.</li>
    <li>without actively changing customer data, unless you explicitly request otherwise.</li>
</ul>

<h2>8. Retention periods</h2>
<p>WinProx applies the following retention periods:</p>
<ul>
    <li>user accounts: active + 24 months</li>
    <li>issues and tasks: contract period + 36 months</li>
    <li>logs: 6 months</li>
    <li>onboarding events per user (for onboarding statistics): 6 months; aggregated onboarding figures without personal data may be retained longer</li>
    <li>media (photos): 24 months after closing the relevant issue or task</li>
    <li>ESG measurements: same retention as issues and tasks (contract period + 36 months)</li>
    <li>IoT events, gateway and sensor metadata: contract period + 36 months (or shorter if the underlying issue/task is removed earlier upon organisation deletion)</li>
    <li>operational infrastructure backups (hosting/Cloud86): 7 days</li>
    <li>technical SQL snapshot after a full organisation deletion (without media files): maximum 30 days, then destruction</li>
</ul>
<p>
    After a full organisation deletion (see below), the tenant’s live data is hard-deleted;
    media files (photos, documents) are not included in the recovery snapshot.
</p>

<h2>9. Sharing of data</h2>
<p>Personal data is not sold or shared with third parties, except:</p>
<ul>
    <li>on the customer’s instructions.</li>
    <li>for hosting and technical infrastructure.</li>
    <li>for payment processing, if you choose to use it (via a recognised payment partner).</li>
    <li>where legally required.</li>
</ul>
<p>
    An overview of subprocessor categories is available on the
    <a href="{{ route('legal.subprocessors') }}">{{ __('legal.documents.subprocessors') }}</a> page.
</p>

<h2>10. International availability</h2>
<p>
    WinProx is an international platform and may be used in multiple countries.
</p>
<p>
    The platform may be available in several languages, including Dutch, English, French, German, Spanish and Italian.
</p>
<p>
    Regardless of the language version, this privacy policy applies to the processing of personal data.
</p>

<h2>11. Data subject rights</h2>
<p>Data subjects have the right to:</p>
<ul>
    <li>access their data.</li>
    <li>rectify their data.</li>
    <li>request erasure of their data.</li>
    <li>object to processing.</li>
</ul>

<p><strong>How the platform supports this</strong></p>
<ul>
    <li>
        <strong>Access / export:</strong> an administrator can download a machine-readable export (JSON in a ZIP)
        under <em>Settings → Privacy &amp; data export</em> for their own account and relevant organisation data.
        Downloads are logged.
    </li>
    <li>
        <strong>Rectification:</strong> authorised users can update their profile (name, email, language);
        administrators can update organisation details.
    </li>
    <li>
        <strong>Deactivate a user:</strong> an administrator can deactivate or pause colleague accounts
        (login is blocked; sessions are revoked). This is not a full organisation wipe.
    </li>
    <li>
        <strong>Delete organisation data (self-service):</strong> administrators only, via
        <em>Subscription → Delete organisation data</em>. An export is offered first; then confirmation with password
        and email to all administrators.
        <ul>
            <li><strong>Trial:</strong> after email confirmation the administrator can wipe definitively
                (technical SQL snapshot without media, kept max. 30 days).</li>
            <li><strong>Paid subscription / grace:</strong> after confirmation a 7-day cooling-off period applies
                (in-app banner, reminder email about 2 days before execution); WinProx administration (superuser)
                performs the deletion. Cancellation is possible until then via Subscription.</li>
        </ul>
    </li>
    <li>
        <strong>Expired trial without subscription:</strong> after the trial ends, login may be limited to
        subscription/billing pages. Without a subscription, WinProx sends warning emails and may delete the
        organisation automatically (default: warning around day 7, deletion around day 14 after trial end).
        Activating a subscription cancels a pending automatic deletion.
    </li>
</ul>

<p>Other or exceptional requests (e.g. litigation hold) may be sent to:</p>
@include('partials.wp-legal-operator')

<p>
    Where data is processed on a customer’s instructions, it may be necessary to handle the request via that customer.
</p>

<h2>12. Security</h2>
<p>WinProx implements appropriate technical and organisational measures, including:</p>
<ul>
    <li>tenant isolation</li>
    <li>access control</li>
    <li>logging</li>
    <li>automatic daily backups via the hosting provider (Cloud86), retained for 7 days</li>
    <li>recovery targets: RPO ≈ 24 hours (max. data loss since the last nightly backup); RTO best effort, typically within 1 business day</li>
</ul>
<p>
    See also the <a href="{{ route('legal.cookies') }}">{{ __('legal.documents.cookies') }}</a> for information on
    strictly necessary cookies.
</p>

<h2>13. International transfers</h2>
<p>
    Data is generally processed within the European Union.
</p>
<p>
    Where external service providers are used, appropriate safeguards are put in place.
</p>

<h2>14. Supervisory authority</h2>
<p>
    You have the right to lodge a complaint with a supervisory authority. In Belgium, this is the Data Protection Authority
    (<a href="https://www.gegevensbeschermingsautoriteit.be" rel="noopener noreferrer" target="_blank">www.gegevensbeschermingsautoriteit.be</a>).
</p>

<h2>15. Changes</h2>
<p>
    This privacy policy may be updated.
</p>
<p>
    The most recent version is always available via the platform.
</p>
