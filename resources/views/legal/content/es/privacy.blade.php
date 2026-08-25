<h2>1. Quiénes somos</h2>
<p>
    WinProx («Work in Proximity») es una plataforma SaaS para la gestión técnica y operativa de instalaciones:
    informes de incidencias mediante QR y seguimiento de tareas para equipos operativos internos, y registro
    ESG/cumplimiento opcional e IoT Connect (eventos de sensores al flujo de trabajo).
</p>
<p>
    La plataforma está operada por:
</p>

@include('partials.wp-legal-operator')

<h2>2. Roles en virtud del RGPD (UE y Bélgica)</h2>
<p>En la plataforma se aplican los siguientes roles:</p>
<ul>
    <li>El cliente / administrador es el responsable del tratamiento.</li>
    <li>WinProx es el encargado del tratamiento.</li>
</ul>
<p>Esto significa que:</p>
<ul>
    <li>El cliente decide qué datos personales se tratan y con qué finalidad.</li>
    <li>WinProx trata los datos personales únicamente siguiendo las instrucciones del cliente.</li>
</ul>

<h2>3. Datos que tratamos</h2>

<p><strong>Usuarios</strong></p>
<ul>
    <li>nombre</li>
    <li>dirección de correo electrónico</li>
    <li>rol dentro de la organización</li>
    <li>preferencia de idioma, cuando proceda</li>
</ul>

<p><strong>Inicio de sesión con Microsoft (opcional)</strong></p>
<p>
    Administradores y empleados pueden iniciar sesión en la pantalla de escritorio a través de Microsoft
    (Microsoft Entra ID), además del correo y la contraseña. WinProx no crea cuentas nuevas:
    el correo de la cuenta Microsoft debe coincidir con un usuario WinProx existente y activo
    (administrador o empleado). Los ejecutores y los invitados no usan este inicio de sesión.
</p>
<ul>
    <li>el usuario es redirigido a Microsoft para identificarse;</li>
    <li>WinProx recibe datos identificativos de Microsoft (normalmente correo y nombre) para asociar la cuenta existente;</li>
    <li>las contraseñas de las cuentas Microsoft no se almacenan en WinProx en este flujo;</li>
    <li>la contraseña WinProx existente se mantiene (p. ej. para recuperación y eliminación de la organización).</li>
</ul>

<p><strong>Suscripción y facturación</strong></p>
<ul>
    <li>plan de suscripción seleccionado (cuando proceda)</li>
    <li>fecha de finalización del periodo de prueba y de la suscripción de pago</li>
    <li>datos de facturación y pago introducidos por usted o su organización, o tratados a través de un proveedor de pagos</li>
</ul>

<p><strong>Ubicaciones y unidades</strong></p>
<ul>
    <li>ubicaciones (instalaciones) y unidades dentro de su organización</li>
    <li>direcciones y datos de ubicación que usted introduce</li>
</ul>

<p><strong>Incidencias y tareas</strong></p>
<ul>
    <li>incidencias y tareas</li>
    <li>descripciones, estados y seguimiento</li>
    <li>comunicación e historial dentro de la plataforma</li>
    <li>fotos y archivos adjuntos añadidos a incidencias o tareas</li>
    <li>comprobaciones de unit (OK/No OK vía QR de la unit por ejecutores), si están activadas en categoría y unit.</li>
</ul>

<p><strong>Trabajadores (sin inicio de sesión)</strong></p>
<ul>
    <li>nombre o nombre visible</li>
    <li>datos de contacto (como la dirección de correo electrónico), cuando los introduce el cliente</li>
    <li>asignación a tareas dentro de equipos internos</li>
</ul>
<p>
    Estos datos los gestiona el cliente / administrador. WinProx no tiene control sustancial sobre lo que introduce el cliente.
</p>

<p><strong>Informes QR</strong></p>
<ul>
    <li>datos enviados voluntariamente a través de un portal QR público (como nombre, dirección de correo electrónico o descripción)</li>
    <li>metadatos técnicos necesarios para la seguridad y la prevención de abusos</li>
</ul>
<p>
    El cliente puede exigir confirmación por correo por categoría y unidad. WinProx guarda entonces el borrador (descripción, fotos, correo) hasta que el informante pulse el enlace del correo. Sin confirmación el borrador se elimina automáticamente (normalmente en 60 minutos), fotos incluidas. Esto demuestra que el buzón existe; no es una cuenta de usuario.
</p>
<p><strong>Comprobaciones de unit</strong></p>
<p>
    Si el cliente activa comprobaciones de unit en categoría y unit, los ejecutores pueden registrar OK o No OK vía el QR de la unit (tras Clock Point), opcionalmente con checklist y GPS. No es un aviso: OK no crea issue. WinProx guarda resultado, marca de tiempo, unit, coordenadas GPS opcionales y ejecutor. Retención igual que avisos y tareas, salvo política interna distinta.
</p>


<p><strong>ESG y cumplimiento (módulo opcional)</strong></p>
<p>
    Si el cliente activa el módulo ESG opcional, pueden registrarse valores de medición y datos de cumplimiento,
    por ejemplo en inspecciones recurrentes, al completar tareas en el portal QR, mediante la API o — si
    IoT Connect está activado — a partir de eventos de sensores.
</p>
<ul>
    <li>definiciones de indicadores (nombre, tipo, unidad, umbrales, opciones), incluidas posibles traducciones de textos de indicadores.</li>
    <li>valores de medición (número, sí/no, elección o texto) con marca de tiempo.</li>
    <li>vínculo con incidencia, tarea, ubicación, unidad y opcionalmente el trabajador; en la ruta de sensor puede no haber tarea.</li>
    <li>correcciones como nuevas filas (append-only); los valores anteriores se conservan.</li>
    <li>alarmas de umbral y tareas de seguimiento resultantes cuando una medición queda fuera de los límites configurados.</li>
    <li>creación de mediciones por API y webhooks opcionales (p. ej. al registrar una nueva fila), si el cliente los conecta.</li>
</ul>
<p>
    El módulo es opcional y solo visible para administradores cuando está activado. El cliente es responsable
    del contenido y uso de los datos ESG dentro de su organización.
</p>

<p><strong>IoT Connect (módulo opcional)</strong></p>
<p>
    Si el cliente activa IoT Connect, las pasarelas pueden enviar eventos a WinProx. WinProx no es una nube IoT ni
    una plataforma de series temporales: el cliente (o su partner de hardware) gestiona pasarelas y sensores; WinProx
    convierte los eventos entrantes en flujo de trabajo dentro del inquilino.
</p>
<ul>
    <li>configuración de pasarelas y tokens de autenticación (almacenados de forma segura; un token nuevo suele mostrarse una sola vez).</li>
    <li>mapeos de sensores (id externo → ubicación/unidad, opcionalmente un indicador ESG).</li>
    <li>reglas de alarma (umbrales, operador, equipo asignado, prioridad, texto).</li>
    <li>registros de eventos (procesado / ignorado / deduplicado / fallido) — sin almacenamiento continuo de series temporales.</li>
    <li>en alarma: una incidencia y tarea aprobadas en la organización (con deduplicación mientras exista una tarea abierta para la misma regla).</li>
    <li>en medición (Corporate, con módulo ESG): una fila de medición ESG basada en el evento del sensor.</li>
</ul>
<p>
    Los datos personales en los flujos IoT se limitan a lo que configura el cliente (p. ej. asignación a equipos/trabajadores
    mediante incidencias y tareas). El cliente sigue siendo responsable de las fuentes de sensores y del contenido de los eventos.
</p>

<h2>4. Traducciones con IA</h2>
<p>La plataforma utiliza traducciones con IA para la visualización multilingüe:</p>
<ul>
    <li>traducción de textos mostrados en varios idiomas en la plataforma o el portal QR (incluidas incidencias, tareas, unidades, anuncios, descripciones de documentos, ubicaciones, categorías, nombres de equipos y textos de indicadores ESG); los textos se ponen en cola para traducción tras la aprobación.</li>
    <li>mediante una instancia local de Ollama (sin servicios / nube de IA externos).</li>
    <li>WinProx ejecuta estas traducciones de forma periódica (habitualmente a diario), sin plazo garantizado.</li>
    <li>las traducciones se almacenan y conservan conforme a la política de retención; los administradores de la organización pueden corregirlas o completarlas manualmente en la plataforma.</li>
    <li>no hay un interruptor de activación/desactivación por organización; WinProx puede pausar el pipeline de traducción a nivel de plataforma.</li>
</ul>

<h2>5. Finalidades del tratamiento</h2>
<p>Los datos se tratan para:</p>
<ul>
    <li>operar la plataforma, incluido el inicio de sesión de administradores y empleados (correo + contraseña y, de forma opcional, Microsoft Entra ID).</li>
    <li>registrar y dar seguimiento a incidencias y tareas.</li>
    <li>asignar trabajo a equipos internos y trabajadores.</li>
    <li>informes QR y comunicación entre usuarios dentro de su organización.</li>
    <li>enviar notificaciones por correo electrónico siguiendo las instrucciones del cliente.</li>
    <li>mejora del producto mediante estadísticas de incorporación de superusuarios (agregadas cuando sea posible).</li>
    <li>seguridad y registro de actividad.</li>
    <li>soporte multilingüe mediante traducciones con IA (ejecutadas periódicamente por WinProx, sin plazo garantizado).</li>
    <li>registro y seguimiento de mediciones ESG/cumplimiento (si el módulo está activado).</li>
    <li>procesamiento de eventos IoT en incidencias, tareas y/o mediciones ESG (si IoT Connect está activado).</li>
</ul>

<h2>6. Informes QR y acceso de equipos</h2>
<p>
    Los códigos QR permiten a los informantes enviar incidencias sin cuenta. El cliente / administrador decide qué ubicaciones
    y unidades están disponibles y qué datos se solicitan. El cliente puede exigir confirmación por correo: hasta que el
    informante pulse el enlace, no hay aviso — solo un borrador temporal.
</p>
<p>
    Los usuarios con sesión iniciada y los equipos internos tienen acceso según los permisos establecidos por el cliente. WinProx trata
    los datos personales en este contexto únicamente como encargado técnico que actúa siguiendo las instrucciones del cliente.
</p>

<h2>7. Soporte y acceso</h2>
<p>
    Para soporte técnico, WinProx puede, en casos excepcionales, acceder a datos mediante un modo de soporte para superusuarios o personal de soporte:
</p>
<ul>
    <li>únicamente para soporte técnico y resolución de incidencias.</li>
    <li>acceso de solo lectura por defecto.</li>
    <li>sin modificar activamente los datos del cliente, salvo que usted lo solicite expresamente.</li>
</ul>

<h2>8. Plazos de conservación</h2>
<p>WinProx aplica los siguientes plazos de conservación:</p>
<ul>
    <li>cuentas de usuario: activas + 24 meses</li>
    <li>incidencias y tareas: duración del contrato + 36 meses</li>
    <li>comprobaciones de unidad: mismo plazo que avisos y tareas (duración del contrato + 36 meses).</li>
    <li>avisos QR no confirmados (retención de correo): hasta que caduque el enlace de confirmación (normalmente 60 minutos), luego eliminación incluidas las fotos; filas de retención confirmadas hasta 7 días (el aviso en sí sigue el plazo de los avisos).</li>
    <li>registros: 6 meses</li>
    <li>eventos de incorporación por usuario (para estadísticas de incorporación): 6 meses; las cifras agregadas de incorporación sin datos personales pueden conservarse más tiempo</li>
    <li>medios (fotos): 24 meses tras cerrar la incidencia o tarea correspondiente</li>
    <li>mediciones ESG: mismo plazo que incidencias y tareas (duración del contrato + 36 meses)</li>
    <li>eventos IoT, metadatos de pasarela y sensor: duración del contrato + 36 meses (o menos si la incidencia/tarea subyacente se elimina antes al borrar la organización)</li>
    <li>copias de seguridad operativas de infraestructura (hosting/Cloud86): 7 días</li>
    <li>instantánea SQL técnica tras la eliminación completa de la organización (sin archivos multimedia): máximo 30 días, después destrucción</li>
</ul>
<p>
    Tras una eliminación completa de la organización (véase más abajo), los datos en vivo del inquilino se eliminan de forma definitiva;
    los archivos multimedia (fotos, documentos) no forman parte de la instantánea de recuperación.
</p>

<h2>9. Comunicación de datos</h2>
<p>Los datos personales no se venden ni se comunican a terceros, excepto:</p>
<ul>
    <li>siguiendo las instrucciones del cliente.</li>
    <li>para alojamiento e infraestructura técnica.</li>
    <li>para el procesamiento de pagos, si usted decide utilizarlo (a través de un socio de pagos reconocido).</li>
    <li>para el inicio de sesión vía Microsoft Entra ID, cuando el usuario elige Iniciar sesión con Microsoft.</li>
    <li>cuando la ley lo exija.</li>
</ul>
<p>
    Un resumen de las categorías de subencargados está disponible en la página
    <a href="{{ route('legal.subprocessors') }}">{{ __('legal.documents.subprocessors') }}</a>.
</p>

<h2>10. Disponibilidad internacional</h2>
<p>
    WinProx es una plataforma internacional y puede utilizarse en varios países.
</p>
<p>
    La plataforma puede estar disponible en varios idiomas, entre ellos neerlandés, inglés, francés, alemán, español e italiano.
</p>
<p>
    Independientemente de la versión lingüística, esta política de privacidad se aplica al tratamiento de datos personales.
</p>

<h2>11. Derechos de los interesados</h2>
<p>Los interesados tienen derecho a:</p>
<ul>
    <li>acceder a sus datos.</li>
    <li>rectificar sus datos.</li>
    <li>solicitar la supresión de sus datos.</li>
    <li>oponerse al tratamiento.</li>
</ul>

<p><strong>Cómo lo permite la plataforma</strong></p>
<ul>
    <li>
        <strong>Acceso / exportación:</strong> un administrador puede descargar una exportación legible por máquina (JSON en un ZIP)
        en <em>Ajustes → Privacidad y exportación de datos</em> de su cuenta y de datos relevantes de la organización.
        Las descargas se registran.
    </li>
    <li>
        <strong>Rectificación:</strong> los usuarios autorizados pueden actualizar su perfil (nombre, correo, idioma);
        los administradores pueden actualizar los datos de la organización.
    </li>
    <li>
        <strong>Desactivar un usuario:</strong> un administrador puede desactivar o pausar cuentas de colegas
        (inicio de sesión bloqueado; sesiones revocadas). No es un borrado completo de la organización.
    </li>
    <li>
        <strong>Eliminar datos de la organización (autoservicio):</strong> solo administradores, mediante
        <em>Suscripción → Eliminar datos de la organización</em>. Primero se ofrece una exportación; después confirmación
        con contraseña y correo a todos los administradores.
        <ul>
            <li><strong>Periodo de prueba:</strong> tras la confirmación por correo, el administrador puede borrar definitivamente
                (instantánea SQL técnica sin multimedia, conservada máx. 30 días).</li>
            <li><strong>Suscripción de pago / gracia:</strong> tras la confirmación hay un plazo de 7 días
                (banner en la app, correo recordatorio unos 2 días antes); la administración de WinProx (superusuario)
                ejecuta la eliminación. Se puede cancelar hasta entonces vía Suscripción.</li>
        </ul>
    </li>
    <li>
        <strong>Prueba caducada sin suscripción:</strong> tras el fin de la prueba, el acceso puede limitarse a páginas
        de suscripción/facturación. Sin suscripción, WinProx envía correos de aviso y puede eliminar la organización
        automáticamente (por defecto: aviso hacia el día 7, eliminación hacia el día 14 tras el fin de la prueba).
        Activar una suscripción cancela una eliminación automática pendiente.
    </li>
</ul>

<p>Otras solicitudes o solicitudes excepcionales (p. ej. litigation hold) pueden enviarse a:</p>
@include('partials.wp-legal-operator')

<p>
    Cuando los datos se tratan siguiendo las instrucciones de un cliente, puede ser necesario gestionar la solicitud a través de dicho cliente.
</p>

<h2>12. Seguridad</h2>
<p>WinProx implementa medidas técnicas y organizativas adecuadas, entre ellas:</p>
<ul>
    <li>aislamiento por inquilino</li>
    <li>control de acceso</li>
    <li>registro de actividad</li>
    <li>copias de seguridad diarias automáticas a través del proveedor de hosting (Cloud86), retenidas 7 días</li>
    <li>objetivos de recuperación: RPO ≈ 24 horas (pérdida máxima desde la última copia nocturna); RTO best effort, normalmente en 1 día laborable</li>
</ul>
<p>
    Consulte también la <a href="{{ route('legal.cookies') }}">{{ __('legal.documents.cookies') }}</a> para información sobre
    las cookies estrictamente necesarias.
</p>

<h2>13. Transferencias internacionales</h2>
<p>
    Los datos se tratan generalmente dentro de la Unión Europea.
</p>
<p>
    Cuando se utilizan proveedores de servicios externos, se adoptan las garantías adecuadas.
</p>

<h2>14. Autoridad de control</h2>
<p>
    Tiene derecho a presentar una reclamación ante una autoridad de control. En Bélgica, se trata de la Autoridad de Protección de Datos
    (<a href="https://www.gegevensbeschermingsautoriteit.be" rel="noopener noreferrer" target="_blank">www.gegevensbeschermingsautoriteit.be</a>).
</p>

<h2>15. Cambios</h2>
<p>
    Esta política de privacidad puede actualizarse.
</p>
<p>
    La versión más reciente está siempre disponible a través de la plataforma.
</p>
