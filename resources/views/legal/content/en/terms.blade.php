<h2>1. General</h2>
<p>
    These terms of service govern use of the WinProx platform.
</p>
<p>
    By using WinProx, the user agrees to these terms.
</p>
<p>
    WinProx (“Work in Proximity”) is a SaaS platform for technical and operational site management:
    QR issue reporting and task follow-up for internal operational teams, and optional
    Time (punch clock), unit measurements, optional ESG/compliance recording and IoT Connect (sensor events into workflow).
</p>

<h2>2. Service provider identity</h2>
<p>WinProx is operated by:</p>

@include('partials.wp-legal-operator')

<h2>3. Description of the service</h2>
<p>
    WinProx provides a digital platform through which customers / administrators can:
</p>
<ul>
    <li>register issues, including via QR portals.</li>
    <li>manage and follow up tasks.</li>
    <li>assign work to internal teams and workers.</li>
    <li>optionally use Time (punch clock): clock in/out via Clock Point QR, with one device bound per worker.</li>
    <li>optionally record and follow up ESG/compliance measurements (if the module is enabled).</li>
    <li>record unit measurements (readings via unit QR) when enabled on category and unit.</li>
    <li>optionally use IoT Connect: link gateways/sensors so alarms and (where applicable) measurements start workflow in WinProx.</li>
</ul>
<p>
    WinProx is a technical platform only and does not perform on-site work itself.
</p>

<h2>4. No performance of work</h2>
<p>WinProx:</p>
<ul>
    <li>does not perform technical or operational work on site.</li>
    <li>does not act as on-site service provider, intermediary or contracting party for operational work.</li>
    <li>does not guarantee outcomes or quality of work performed by your organisation.</li>
</ul>
<p>
    All operational decisions and execution remain the responsibility of the customer / administrator and its internal teams.
</p>

<h2>5. Customer responsibility</h2>
<p>The customer / administrator is responsible for:</p>
<ul>
    <li>the accuracy of entered data.</li>
    <li>use of the platform within the organisation.</li>
    <li>assigning and following up tasks for internal teams and workers.</li>
    <li>compliance with applicable law.</li>
</ul>

<p>
    The customer remains the data controller for personal data processed within its own use of the platform.
</p>

<h2>6. Use of the platform</h2>
<p>It is not permitted to:</p>
<ul>
    <li>use the platform for illegal activities.</li>
    <li>enter false or misleading information.</li>
    <li>misuse communication or notification features.</li>
</ul>

<p>
    WinProx reserves the right to restrict or block accounts in case of abuse.
</p>
<p>
    Admins and employees can sign in with email and password, or via Microsoft
    (Sign in with Microsoft). Microsoft sign-in only matches an existing WinProx account
    (email must match). Workers do not use Microsoft sign-in.
</p>

<h2>7. Service availability</h2>
<p>
    WinProx strives to keep the platform running well but does not guarantee uninterrupted availability.
</p>
<p>
    WinProx may perform maintenance, updates or technical changes.
</p>
<p>
    WinProx is not liable for temporary interruptions.
</p>
<p>
    WinProx uses automatic daily backups via the hosting provider (Cloud86), retained for 7 days.
    Targets: RPO ≈ 24 hours (maximum data loss since the last nightly backup) and RTO best effort, typically within 1 business day.
    This is not an uptime guarantee with liquidated damages. A technical SQL snapshot after full organisation deletion (without media, max. 30 days) is separate from these operational backups.
</p>

<h2>8. Subscription, trial and payment</h2>
<p>
    WinProx may offer a limited trial period. The duration is communicated at registration or on the platform.
</p>
<p>
    After the trial period, continued use requires an appropriate subscription as described on the platform (including based on the number of units and optional modules).
</p>
<p>
    The subscription covers access to and use of the platform for your organisation (tenant). Payment, invoicing and renewal follow the terms shown on the platform or in quotes/invoices.
</p>
<p>
    If payment is not made on time or the subscription expires, WinProx may restrict or suspend access to the platform, where technically provided and subject to reasonable notice where applicable.
</p>
<p>
    After a trial ends without an active subscription, access may remain limited to subscription and billing pages.
    Without a timely subscription, WinProx may delete the organisation automatically after prior email warning
    (default: warning around 7 days and deletion around 14 days after trial end). Activating a subscription
    stops a scheduled automatic deletion.
</p>
<p>
    WinProx may adjust pricing and plans. Relevant changes will be communicated via the platform and/or by email with reasonable advance notice.
</p>
<p>
    For personal data processing in this context, see the
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a> and, between your organisation and WinProx, the
    <a href="{{ route('legal.dpa') }}">{{ __('legal.documents.dpa') }}</a> where applicable.
</p>

<h2>9. Liability</h2>
<p>WinProx is not liable for:</p>
<ul>
    <li>damage arising from work performed by the customer or its internal teams.</li>
    <li>errors in operational decisions or on-site execution.</li>
    <li>indirect damage, including loss of profit, consequential damage or reputational damage.</li>
</ul>

<p>
    To the extent permitted by law, WinProx’s liability in all cases is limited to the amount the customer paid for use of the platform in the twelve months preceding the event giving rise to the claim.
</p>

<h2>10. Data and privacy</h2>
<p>
    Use of personal data is governed by the
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a>.
</p>
<p>
    WinProx processes data on the customer’s / administrator’s instructions.
</p>

<h2>11. Intellectual property</h2>
<p>
    All rights relating to the platform remain the property of WinProx.
</p>
<p>It is not permitted to:</p>
<ul>
    <li>copy the software.</li>
    <li>reuse parts of the platform without prior written consent.</li>
</ul>

<h2>12. Termination</h2>
<p>
    WinProx may terminate or suspend use of the platform:
</p>
<ul>
    <li>in case of breach of these terms.</li>
    <li>in case of abuse of the platform.</li>
</ul>

<p>
    Individual user accounts may be deactivated or paused by the organisation’s administrator
    according to the rights available in the platform.
</p>
<p>
    An administrator may request full organisation deletion via
    <em>Subscription → Delete organisation data</em>, after an export offer, password confirmation and email
    confirmation to all administrators:
</p>
<ul>
    <li><strong>Trial:</strong> after confirmation the administrator can wipe definitively.</li>
    <li><strong>Paid subscription:</strong> 7-day cooling-off period; deletion by WinProx administration; cancellation possible until then via Subscription.</li>
</ul>
<p>
    Upon definitive deletion, a technical SQL snapshot without media files is kept for max. 30 days and then destroyed.
    Details on personal data are set out in the
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a>.
</p>

<h2>13. International availability</h2>
<p>
    WinProx may be offered internationally and available in multiple languages.
</p>
<p>
    Regardless of the language version, use of the platform remains subject to these terms.
</p>

<h2>14. Governing law and jurisdiction</h2>
<p>
    These terms are governed by Belgian law, without prejudice to mandatory EU law.
</p>
<p>
    Disputes fall under the jurisdiction of the courts of the judicial district of the operator, unless mandatory law provides otherwise.
</p>

<h2>15. Changes</h2>
<p>
    WinProx may amend these terms.
</p>
<p>
    The most recent version is always available via the platform.
</p>
