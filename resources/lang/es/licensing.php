<?php

declare(strict_types=1);

return [
    'title' => 'Guía de licencia',
    'license_label' => 'Licencias',
    'scope_internal' => 'Uso personal e interno · Gratis',
    'scope_hosted' => 'SaaS Comercial · Estándar',
    'scope_custom' => 'Distribución · Personalizado',
    'notice_title' => 'La licencia adecuada para tu proyecto.',
    'notice' => 'El uso personal y las herramientas internas calificadas son gratuitos. SaaS comercial necesita una licencia estándar; Las instalaciones de los clientes, los kits reutilizables y los OEM necesitan derechos personalizados acordados.',
    'check_scope' => 'Encuentra tu licencia',
    'intro' => 'Elija cómo planea usar FLEX Fields. Le ayudaremos a identificar el alcance descrito en LICENSE v2.0.',
    'selection_hint' => 'Seleccione cada uso previsto y confirme sus condiciones. Evaluamos cada uso por separado, incluidos sus productos y los productos de un cliente. El recuento de productos sigue identidades de productos independientes, no usuarios, dominios o servidores.',
    'assess' => 'Revisa mi uso',
    'assessing' => 'Comprobando…',
    'result' => 'Guía de licencia',
    'contact' => 'Discuta el alcance y la compra',
    'plans' => 'Planes y compras',
    'client_note' => 'Para aplicaciones personalizadas propiedad del cliente, la licencia pertenece al cliente. La licencia de cartera de una agencia no cubre a sus clientes.',
    'terms_note' => 'Esta guía resume LICENSE v2.0 y COMMERCIAL.md; no emite ni activa una licencia. Prevalecen los términos completos, su confirmación de licencia y cualquier alcance personalizado acordado. Los precios en USD excluyen los impuestos aplicables; El alcance se confirma antes del pago.',
    'existing_note' => '¿Ya tiene licencia? Las licencias existentes conservan su versión y alcance acordados. Esta guía no determina si una concesión previa o personalizada cubre su uso.',
    'breakdown' => 'Valoración por uso',
    'mixed_note' => 'Cada resultado se aplica únicamente al uso que se muestra al lado. Un requisito personalizado para un proyecto no reemplaza ni amplía los derechos necesarios para otro.',
    'client_use' => [
        'internal' => [
            'label' => 'Operaciones internas del cliente',
            'description' => 'Una aplicación personalizada para las operaciones propias de ese cliente, no un producto de software que suministra a otros.',
        ],
        'hosted' => [
            'label' => 'Producto alojado comercial del cliente',
            'description' => 'El cliente es propietario de su SaaS y los clientes reciben únicamente acceso al tiempo de ejecución. El cliente necesita su propia licencia adecuada.',
        ],
        'distribution' => [
            'label' => 'El cliente distribuye software o instalaciones del cliente',
            'description' => 'El cliente suministra copias que contienen paquetes, kits reutilizables, instalaciones o derechos OEM independientes a sus propios clientes.',
        ],
    ],
    'questions' => [
        'client_use' => '¿Cómo utilizará el cliente su aplicación?',
        'client_ownership' => '¿Quién posee y opera los productos alojados del cliente?',
        'client_products' => '¿Cuántos productos alojados posee el cliente?',
        'uses' => '¿Para qué usarás FLEX?',
        'conditions' => 'Confirmar las condiciones pertinentes',
        'ownership' => '¿Quién posee y opera sus productos alojados?',
        'products' => '¿Cuántos productos alojados posee?',
    ],
    'uses' => [
        'personal' => [
            'label' => 'Uso personal, educativo o de investigación',
            'description' => 'Un proyecto, aprendizaje o investigación privado, en lugar de operar un producto de software comercial.',
        ],
        'internal' => [
            'label' => 'Mis propias operaciones comerciales',
            'description' => 'CRM de personal, tienda, reservas o administración de empresas. Un back office califica solo cuando administra el negocio independientemente del servicio de software que usted vende.',
        ],
        'evaluation' => [
            'label' => 'Desarrollo y evaluación privados',
            'description' => 'Probar un producto potencial antes de las operaciones reales del cliente, los pilotos pagados o la producción.',
        ],
        'bespoke_runtime' => [
            'label' => 'Herramienta interna a medida operada para un cliente',
            'description' => 'Un contrato de trabajo por contrato documentado; usted lo opera y el cliente recibe acceso de inicio de sesión/API únicamente.',
        ],
        'bespoke_client' => [
            'label' => 'Aplicación personalizada propiedad del cliente',
            'description' => 'El cliente obtiene el paquete oficial por derecho propio; usted trabaja como su contratista autorizado.',
        ],
        'hosted' => [
            'label' => 'Producto alojado comercial/SaaS',
            'description' => 'Un servicio de software monetizado o freemium. También pertenece a este ámbito una pantalla exclusiva para el personal que configura dicho servicio.',
        ],
        'distribution' => [
            'label' => 'Instalaciones del cliente o entrega de paquete/fuente',
            'description' => 'Su propio producto en el servidor/nube, contenedores o fuente que contiene paquetes de un cliente. Excluye la ruta genuina y personalizada de cliente directo mencionada anteriormente.',
        ],
        'kits' => [
            'label' => 'Kit de inicio, plantilla o desarrollador reutilizable',
            'description' => 'Un producto reutilizable creado alrededor de FLEX Fields, incluso si los compradores instalan ellos mismos la dependencia oficial.',
        ],
        'oem' => [
            'label' => 'OEM, sublicencia o reventa independiente',
            'description' => 'Otra empresa puede instalar, operar de forma independiente, redistribuir o sublicenciar su producto basado en software.',
        ],
    ],
    'conditions' => [
        'free_scope' => [
            'label' => 'El uso personal/interno cumple con §1.7(a)',
            'description' => 'No suministrar la aplicación en sí como producto de software comercial; retener avisos y permanecer dentro de la licencia.',
        ],
        'evaluation_private' => [
            'label' => 'La evaluación es privada y no productiva',
            'description' => 'No se permiten operaciones con clientes en vivo, piloto pagado ni lanzamiento de producción bajo la excepción de evaluación.',
        ],
        'bespoke_runtime_scope' => [
            'label' => 'La participación de solo tiempo de ejecución cumple con la sección 1.7(b)',
            'description' => 'Cliente identificado y alcance personalizado por escrito; usted sigue siendo un operador responsable; uso interno, sin reventa de software de cliente ni entrega de paquetes.',
        ],
        'direct_client' => [
            'label' => 'La ruta de cliente directo cumple con §5.6',
            'description' => 'Trabajo genuino a medida; El cliente obtiene la dependencia oficial directamente y lo autoriza. No proporcionas tu propia copia del paquete/bifurcación privada ni un producto reutilizable.',
        ],
        'client_free_scope' => [
            'label' => 'El cliente califica de forma independiente para uso interno gratuito',
            'description' => 'El cliente cumple con el §1.7(a) por derecho propio. Utiliza la aplicación para sus operaciones, en lugar de proporcionar un servicio de software a otros.',
        ],
        'hosted_scope' => [
            'label' => 'Los clientes comerciales solo reciben acceso al tiempo de ejecución',
            'description' => 'Para cada uso alojado seleccionado, el licenciatario del producto es propietario y lo opera, directamente o a través de personal autorizado. Los clientes obtienen acceso únicamente al tiempo de ejecución, sin instalación controlada por el cliente, entrega de paquetes/fuentes ni derechos de revendedor independiente.',
        ],
    ],
    'ownership' => [
        'one' => [
            'label' => 'Una persona o entidad jurídica',
            'description' => 'Incluye departamentos y marcas de esa misma entidad; excluye las empresas constituidas por separado.',
        ],
        'group' => [
            'label' => 'Matriz + subsidiarias de propiedad total',
            'description' => '100% de participación en la propiedad y derechos de voto a través de la cadena de calificación de la matriz nombrada.',
        ],
        'other' => [
            'label' => 'Otros propietarios/operadores',
            'description' => 'Empresas de propiedad parcial, empresas hermanas, empresas conjuntas o clientes no relacionados; evaluar sus licencias por separado.',
        ],
    ],
    'products' => [
        'one' => [
            'label' => 'Un producto',
            'description' => 'Se incluyen versiones, idiomas, regiones e instancias de inquilinos del mismo producto.',
        ],
        'two' => [
            'label' => 'Dos productos independientes',
            'description' => 'Identidades de productos separadas, en lugar de módulos o dominios de la misma aplicación.',
        ],
        'portfolio' => [
            'label' => 'Tres o más / cartera futura',
            'description' => 'Múltiples productos ofrecidos de forma independiente, propiedad del licenciatario y operados por él.',
        ],
    ],
    'outcomes' => [
        'mixed' => [
            'title' => 'Diferentes usos necesitan diferente cobertura',
            'detail' => 'No existe una recomendación única que cubra todas las actividades seleccionadas. Revise cada resultado a continuación, incluidas las condiciones que aún requieren confirmación.',
        ],
        'free' => [
            'title' => 'Puede calificar para uso gratuito',
            'detail' => 'No se requiere compra comercial cuando se cumplen todas las condiciones de uso gratuito pertinentes. Aún se aplican restricciones y avisos de redistribución.',
        ],
        'single' => [
            'title' => 'Single Product · $249',
            'detail' => 'Un producto designado y un licenciatario. Licencia Standard de pago único, con usuarios, inquilinos, desarrolladores y entornos ilimitados dentro del alcance acordado.',
        ],
        'two_single' => [
            'title' => 'Dos licencias Single Product · $498',
            'detail' => 'Dos licencias Single Product cuestan menos a los precios actuales. Unlimited Company ($699) también cubre la cartera actual y futura de la misma entidad.',
        ],
        'unlimited' => [
            'title' => 'Unlimited Company · $699',
            'detail' => 'Los productos alojados actuales y futuros elegibles de una entidad designada. A partir de tres productos cuesta menos que tres licencias Single Product; las licencias separadas siguen siendo una opción.',
        ],
        'enterprise' => [
            'title' => 'Enterprise Group · $1,499',
            'detail' => 'Una licencia común para la empresa matriz designada y sus filiales de propiedad total. Pueden servir licencias separadas adecuadas; el alcance del grupo no incluye OEM ni entrega al cliente.',
        ],
        'separate' => [
            'title' => 'Licencias separadas o cobertura negociada',
            'detail' => 'Estas entidades no comparten automáticamente una licencia. Pueden servir licencias Single Product o Unlimited Company adecuadas por propietario, o una cobertura expresamente acordada. Contáctenos con la estructura de propiedad.',
        ],
        'custom' => [
            'title' => 'Personalizado / OEM · cotización individual',
            'detail' => 'Las cotizaciones comienzan en $2999 para un alcance estrictamente especificado, no derechos ilimitados ni una tarifa fija. Los planes estándar no autorizan esta actividad.',
        ],
        'review' => [
            'title' => 'Confirme el alcance antes de continuar',
            'detail' => 'Una condición faltante o ambigua impide un uso gratuito o una recomendación estándar. Contactar al autor con la titularidad y modelo de entrega.',
        ],
        'incomplete' => [
            'title' => 'Se necesita un poco más de información',
            'detail' => 'Seleccione el uso previsto y las opciones de propiedad/producto relevantes.',
        ],
    ],
    'reasons' => [
        'mixed_uses' => 'Su selección combina actividades con diferentes rutas de licencia o condiciones no confirmadas.',
        'combined_uses' => 'Cada uso seleccionado ha sido evaluado por separado; Los resultados a continuación comparten la misma categoría de licencia.',
        'mixed_owners' => 'Sus propios productos alojados y los productos alojados de propiedad del cliente necesitan una cobertura adecuada por separado. La licencia de una agencia no cubre a ambos propietarios.',
        'client_distribution' => 'El cliente necesita derechos específicos expresamente acordados para distribuir el software a sus propios clientes. La excepción para el trabajo a medida de una agencia no otorga esos derechos.',
        'client_free_scope' => 'Confirmar que el cliente cumple de forma independiente todas las condiciones de uso interno aplicables.',
        'choose_use' => 'Elija al menos un uso previsto.',
        'choose_products' => 'Elige el número de productos independientes.',
        'choose_ownership' => 'Identificar al propietario/operador.',
        'distribution' => 'Las copias de clientes, los kits reutilizables y los derechos de revendedores/OEM independientes necesitan un alcance de distribución acordado expresamente.',
        'direct_client' => 'Elija el uso del cliente y confirme las condiciones genuinas y personalizadas directas al cliente. La licencia de una agencia no puede sustituir los derechos del cliente.',
        'hosted_scope' => 'Confirme la propiedad del producto, el alojamiento controlado por el proveedor y el acceso del cliente solo en tiempo de ejecución. La entrega controlada por el cliente sigue una ruta diferente.',
        'group' => 'Los productos comerciales seleccionados abarcan un grupo corporativo de propiedad absoluta calificado.',
        'other_entities' => 'Los propietarios/operadores seleccionados están fuera de una entidad y del grupo de propiedad total calificado.',
        'one_product' => 'El uso seleccionado es un producto comercial alojado propiedad de/operado por un licenciatario.',
        'two_products' => 'Seleccionó dos productos comerciales alojados independientes del mismo licenciatario.',
        'portfolio' => 'Seleccionó tres o más productos, o cobertura de cartera para productos futuros.',
        'evaluation' => 'La evaluación es gratuita solo antes de la producción, las operaciones en vivo del cliente y los pilotos pagos.',
        'bespoke_runtime' => 'Las condiciones de participación documentada, uso interno y acceso solo en tiempo de ejecución deben cumplirse.',
        'free_scope' => 'Confirme que la aplicación es para operaciones personales/internas calificadas, no un producto de software suministrado a otros.',
        'client_free' => 'Una aplicación interna genuina y personalizada propiedad del cliente puede calificar bajo el derecho de uso gratuito del propio cliente.',
        'permitted_free' => 'Su uso seleccionado y las condiciones confirmadas se ajustan a las rutas de uso libre resumidas.',
    ],
];
