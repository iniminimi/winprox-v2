<h2>1. Partes</h2>

<p>El presente acuerdo de tratamiento de datos se celebra entre:</p>

<p><strong>Cliente / administrador (inquilino)</strong><br>
(el responsable del tratamiento)</p>

<p>y</p>

@include('partials.wp-legal-operator')

<p>(en adelante «WinProx», el encargado del tratamiento)</p>

<p>
    Este acuerdo se rige por el Reglamento (UE) 2016/679 (RGPD) y la legislación federal belga de protección de datos (Ley de 30 de julio de 2018); implementa el artículo 28 del RGPD para el tratamiento siguiendo las instrucciones del cliente.
</p>

<h2>2. Objeto</h2>

<p>
    WinProx trata datos personales siguiendo las instrucciones del cliente en relación con el uso de la plataforma para
    la gestión de instalaciones, informes de incidencias mediante QR y seguimiento de incidencias y tareas, y — si está
    activado — mediciones ESG/cumplimiento opcionales e IoT Connect (eventos de sensores al flujo de trabajo).
</p>

<h2>3. Finalidad del tratamiento</h2>

<p>El tratamiento incluye:</p>

<ul>
    <li>gestión de incidencias y tareas.</li>
    <li>gestión de usuarios y equipos internos.</li>
    <li>gestión de trabajadores (sin inicio de sesión) y asignación a tareas.</li>
    <li>gestión de ubicaciones y unidades.</li>
    <li>envío de notificaciones por correo electrónico siguiendo las instrucciones del cliente.</li>
    <li>registro de actividad y seguridad.</li>
    <li>registro y seguimiento de mediciones ESG/cumplimiento (si el módulo está activado).</li>
    <li>procesamiento de eventos IoT (alarmas a incidencias/tareas; mediciones a ESG si está configurado).</li>
</ul>

<h2>4. Tipos de datos</h2>

<ul>
    <li>datos de identificación (nombre, dirección de correo electrónico, número de teléfono cuando se introduzcan).</li>
    <li>datos de ubicación y unidades (direcciones, detalles de ubicación).</li>
    <li>datos de incidencias y tareas (incluidas fotos y descripciones).</li>
    <li>datos de trabajadores e informantes QR, en la medida en que los recopile el cliente.</li>
    <li>datos de acceso y sesión.</li>
    <li>metadatos de suscripción y acceso.</li>
    <li>datos ESG/cumplimiento (definiciones de indicadores, valores de medición, vínculos, seguimiento de umbrales y atribución opcional a trabajadores).</li>
    <li>datos IoT (pasarelas, mapeos de sensores, reglas de alarma, estados de eventos; sin volcado de series temporales).</li>
</ul>

<h2>5. Obligaciones de WinProx</h2>

<p>WinProx deberá:</p>

<ul>
    <li>tratar los datos únicamente siguiendo las instrucciones del cliente.</li>
    <li>implementar medidas de seguridad adecuadas.</li>
    <li>restringir el acceso a personas autorizadas.</li>
    <li>garantizar la confidencialidad.</li>
</ul>

<h2>6. Seguridad</h2>

<p>WinProx proporciona, entre otras cosas:</p>

<ul>
    <li>aislamiento por inquilino.</li>
    <li>control de acceso.</li>
    <li>registro de actividad.</li>
</ul>

<h2>7. {{ __('legal.documents.subprocessors') }}</h2>

<p>
    WinProx puede utilizar terceros para alojamiento, infraestructura, correo electrónico y (cuando se utilice) pagos.
</p>

<p>
    Estas partes se seleccionan cuidadosamente y están sujetas a garantías contractuales adecuadas. Un resumen actualizado está disponible en
    <a href="{{ route('legal.subprocessors') }}">{{ __('legal.documents.subprocessors') }}</a>.
</p>

<h2>8. Violaciones de datos</h2>

<p>
    WinProx informará al cliente sin dilación indebida en caso de una violación de seguridad de datos personales.
</p>

<h2>9. Derechos de los interesados</h2>

<p>
    WinProx apoya al cliente en la gestión de solicitudes de los interesados, entre otros mediante
    funciones de la plataforma para exportación (Ajustes → Privacidad y exportación de datos), desactivación de usuarios
    y eliminación autoservicio de la organización (Suscripción), según se describe en la
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a>.
</p>

<h2>10. Plazos de conservación</h2>

<p>
    Los datos se conservan conforme a la política de retención descrita en la
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a>, incluidos:
</p>
<ul>
    <li>cuentas de usuario: activas + 24 meses.</li>
    <li>incidencias y tareas: duración del contrato + 36 meses.</li>
    <li>registros: 6 meses.</li>
    <li>fotos: 24 meses tras el cierre.</li>
    <li>mediciones ESG: mismo plazo que incidencias y tareas.</li>
    <li>eventos IoT y metadatos de pasarela/sensor: duración del contrato + 36 meses (salvo eliminación anterior del inquilino).</li>
    <li>instantánea SQL técnica tras la eliminación completa de la organización (sin multimedia): máx. 30 días.</li>
</ul>

<h2>11. Fin del acuerdo</h2>

<p>
    Tras la finalización del uso de la plataforma:
</p>

<ul>
    <li>el cliente puede exportar datos mediante la plataforma (JSON/ZIP) antes de la eliminación.</li>
    <li>el cliente (administrador) puede iniciar una eliminación completa del inquilino en autoservicio (prueba: ejecución tras confirmación por correo; de pago: plazo de 7 días y ejecución por la administración de WinProx).</li>
    <li>WinProx puede, ante una prueba caducada sin suscripción y tras aviso, eliminar la organización automáticamente.</li>
    <li>los datos en vivo se eliminan de forma definitiva; una instantánea SQL técnica sin archivos multimedia se conserva máx. 30 días y después se destruye.</li>
    <li>los demás datos se eliminan o anonimizan conforme a la política de retención, sin perjuicio de obligaciones legales de conservación o litigation holds.</li>
</ul>

<h2>12. Responsabilidad</h2>

<p>
    La responsabilidad de WinProx está limitada según lo establecido en las
    <a href="{{ route('legal.terms') }}">{{ __('legal.documents.terms') }}</a>.
</p>

<h2>13. Ley aplicable</h2>

<p>
    Este acuerdo se rige por la legislación belga.
</p>
