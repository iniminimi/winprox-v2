<h2>1. Parties</h2>

<p>This data processing agreement is entered into between:</p>

<p><strong>Customer / administrator (tenant)</strong><br>
(the data controller)</p>

<p>and</p>

@include('partials.wp-legal-operator')

<p>(hereinafter “WinProx”, the processor)</p>

<p>
    This agreement is governed by Regulation (EU) 2016/679 (GDPR) and Belgian federal data protection law (Act of 30 July 2018); it implements Article 28 GDPR for processing on the customer’s instructions.
</p>

<h2>2. Subject matter</h2>

<p>
    WinProx processes personal data on the customer’s instructions in connection with use of the platform for
    site management, QR issue reporting and follow-up of issues and tasks, and — if enabled — optional
    ESG/compliance measurements and IoT Connect (sensor events into workflow).
</p>

<h2>3. Purpose of processing</h2>

<p>Processing includes:</p>

<ul>
    <li>management of issues and tasks.</li>
    <li>recording of unit checks (OK/Not OK via unit QR), when enabled on category and unit.</li>
    <li>management of users and internal teams.</li>
    <li>management of workers (without login) and assignment to tasks.</li>
    <li>management of locations and units.</li>
    <li>sending email notifications on the customer’s instructions.</li>
    <li>logging and security.</li>
    <li>recording and follow-up of ESG/compliance measurements (if the module is enabled).</li>
    <li>processing of IoT events (alarms into issues/tasks; measurements into ESG where configured).</li>
</ul>

<h2>4. Types of data</h2>

<ul>
    <li>identification data (name, email address, phone number where entered).</li>
    <li>location and unit data (addresses, location details).</li>
    <li>issue and task data (including photos and descriptions).</li>
    <li>unit check data (result, timestamp, unit, optional GPS, worker).</li>
    <li>data of workers and QR reporters, to the extent collected by the customer.</li>
    <li>access and session data.</li>
    <li>subscription and access metadata.</li>
    <li>ESG/compliance data (indicator definitions, measurement values, links, threshold follow-up and optional attribution to workers).</li>
    <li>IoT data (gateways, sensor mappings, alarm rules, event statuses; no time-series dump).</li>
</ul>

<h2>5. WinProx obligations</h2>

<p>WinProx shall:</p>

<ul>
    <li>process data only on the customer’s instructions.</li>
    <li>implement appropriate security measures.</li>
    <li>restrict access to authorised persons.</li>
    <li>ensure confidentiality.</li>
</ul>

<h2>6. Security</h2>

<p>WinProx provides, among other things:</p>

<ul>
    <li>tenant isolation.</li>
    <li>access control.</li>
    <li>logging.</li>
    <li>automatic daily backups via the hosting provider (Cloud86), retained for 7 days.</li>
    <li>recovery targets: RPO ≈ 24 hours (max. data loss since the last nightly backup); RTO best effort, typically within 1 business day.</li>
</ul>

<h2>7. {{ __('legal.documents.subprocessors') }}</h2>

<p>
    WinProx may use third parties for hosting, infrastructure, email and (where used) payments.
</p>

<p>
    These parties are carefully selected and subject to appropriate contractual safeguards. An up-to-date overview is available at
    <a href="{{ route('legal.subprocessors') }}">{{ __('legal.documents.subprocessors') }}</a>.
</p>

<h2>8. Data breaches</h2>

<p>
    WinProx will inform the customer without undue delay in the event of a personal data breach.
</p>

<h2>9. Data subject rights</h2>

<p>
    WinProx supports the customer in handling requests from data subjects, including through
    platform features for export (Settings → Privacy &amp; data export), user deactivation
    and self-service organisation deletion (Subscription), as described in the
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a>.
</p>

<h2>10. Retention periods</h2>

<p>
    Data is retained according to the retention policy described in the
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a>, including:
</p>
<ul>
    <li>user accounts: active + 24 months.</li>
    <li>issues and tasks: contract period + 36 months.</li>
    <li>unit checks: same retention as issues and tasks.</li>
    <li>logs: 6 months.</li>
    <li>photos: 24 months after closing.</li>
    <li>ESG measurements: same retention as issues and tasks.</li>
    <li>IoT events and gateway/sensor metadata: contract period + 36 months (unless earlier tenant deletion).</li>
    <li>operational infrastructure backups (hosting/Cloud86): 7 days.</li>
    <li>technical SQL snapshot after full organisation deletion (without media): max. 30 days.</li>
</ul>

<h2>11. End of agreement</h2>

<p>
    Upon termination of use of the platform:
</p>

<ul>
    <li>the customer may export data via the platform (JSON/ZIP) before deletion.</li>
    <li>the customer (administrator) may start a full tenant deletion via self-service (trial: execute after email confirmation; paid: 7-day cooling-off and execution by WinProx administration).</li>
    <li>WinProx may, for an expired trial without subscription and after warning, delete the organisation automatically.</li>
    <li>live data is hard-deleted; a technical SQL snapshot without media files is kept for max. 30 days and then destroyed.</li>
    <li>other data is deleted or anonymised according to the retention policy, subject to legal retention duties or litigation holds.</li>
</ul>

<h2>12. Liability</h2>

<p>
    WinProx’s liability is limited as set out in the
    <a href="{{ route('legal.terms') }}">{{ __('legal.documents.terms') }}</a>.
</p>

<h2>13. Governing law</h2>

<p>
    This agreement is governed by Belgian law.
</p>
