<?php

declare(strict_types=1);

return [
    'title' => 'Lizenzleitfaden',
    'license_label' => 'Lizenzierung',
    'scope_internal' => 'Persönlicher und interner Gebrauch · Kostenlos',
    'scope_hosted' => 'Kommerzielles SaaS · Standard',
    'scope_custom' => 'Verteilung · Benutzerdefiniert',
    'notice_title' => 'Die richtige Lizenz für Ihr Projekt.',
    'notice' => 'Die persönliche Nutzung und qualifizierende interne Tools sind kostenlos. Für kommerzielles SaaS ist eine Standardlizenz erforderlich. Kundeninstallationen, wiederverwendbare Kits und OEMs benötigen vereinbarte kundenspezifische Rechte.',
    'check_scope' => 'Finden Sie Ihre Lizenz',
    'intro' => 'Wählen Sie aus, wie Sie FLEX Fields verwenden möchten. Wir helfen Ihnen, den in LICENSE v2.0 beschriebenen Geltungsbereich zu ermitteln.',
    'selection_hint' => 'Wählen Sie jeden Verwendungszweck aus und bestätigen Sie dessen Bedingungen. Wir bewerten jede Verwendung separat, einschließlich Ihrer Produkte und der Produkte eines Kunden. Die Produktanzahl richtet sich nach unabhängigen Produktidentitäten, nicht nach Benutzern, Domänen oder Servern.',
    'assess' => 'Überprüfen Sie meine Verwendung',
    'assessing' => 'Überprüfen…',
    'result' => 'Ihr Lizenzleitfaden',
    'contact' => 'Besprechen Sie Umfang und Kauf',
    'plans' => 'Pläne und Kauf',
    'client_note' => 'Bei maßgeschneiderten kundeneigenen Anwendungen gehört die Lizenz dem Kunden. Die Portfoliolizenz einer Agentur deckt ihre Kunden nicht ab.',
    'terms_note' => 'Dieses Handbuch fasst LICENSE v2.0 und COMMERCIAL.md zusammen; Es wird keine Lizenz ausgestellt oder aktiviert. Es gelten die vollständigen Bedingungen, Ihre Lizenzbestätigung und alle vereinbarten benutzerdefinierten Geltungsbereiche. USD-Preise verstehen sich zuzüglich der geltenden Steuern; Der Umfang wird vor der Zahlung bestätigt.',
    'existing_note' => 'Bereits lizenziert? Bestehende Lizenzrechte behalten ihre vereinbarte Fassung und ihren vereinbarten Umfang. Dieser Leitfaden beurteilt nicht, ob Ihre Nutzung durch eine frühere oder individuell vereinbarte Lizenz abgedeckt ist.',
    'breakdown' => 'Bewertung nach Verwendung',
    'mixed_note' => 'Jedes Ergebnis gilt nur für die daneben angezeigte Verwendung. Eine benutzerdefinierte Anforderung für ein Projekt ersetzt oder erweitert nicht die für ein anderes Projekt erforderlichen Rechte.',
    'client_use' => [
        'internal' => [
            'label' => 'Interne Abläufe des Kunden',
            'description' => 'Eine maßgeschneiderte Anwendung für den eigenen Betrieb des Kunden, kein Softwareprodukt, das er an andere liefert.',
        ],
        'hosted' => [
            'label' => 'Kommerziell gehostetes Produkt des Kunden',
            'description' => 'Der Kunde ist Eigentümer seines SaaS und Kunden erhalten nur Laufzeitzugriff. Der Kunde benötigt eine eigene, passende Lizenz.',
        ],
        'distribution' => [
            'label' => 'Der Kunde vertreibt Software oder Kundeninstallationen',
            'description' => 'Der Kunde liefert pakethaltige Kopien, wiederverwendbare Kits, Installationen oder unabhängige OEM-Rechte an seine eigenen Kunden.',
        ],
    ],
    'questions' => [
        'client_use' => 'Wie wird der Kunde seine Anwendung nutzen?',
        'client_ownership' => 'Wer besitzt und betreibt die gehosteten Produkte des Kunden?',
        'client_products' => 'Wie viele gehostete Produkte besitzt der Kunde?',
        'uses' => 'Wofür werden Sie FLEX verwenden?',
        'conditions' => 'Bestätigen Sie die relevanten Bedingungen',
        'ownership' => 'Wer besitzt und betreibt Ihre gehosteten Produkte?',
        'products' => 'Wie viele gehostete Produkte besitzen Sie?',
    ],
    'uses' => [
        'personal' => [
            'label' => 'Persönliche, pädagogische oder wissenschaftliche Nutzung',
            'description' => 'Ein privates Projekt, Lernen oder Forschen, anstatt ein kommerzielles Softwareprodukt zu betreiben.',
        ],
        'internal' => [
            'label' => 'Mein eigener Geschäftsbetrieb',
            'description' => 'Ihr Personal CRM, Shop, Buchungen oder Geschäftsverwaltung. Ein Backoffice ist nur dann qualifiziert, wenn es das Geschäft unabhängig von einem von Ihnen verkauften Softwaredienst verwaltet.',
        ],
        'evaluation' => [
            'label' => 'Private Entwicklung und Bewertung',
            'description' => 'Testen eines potenziellen Produkts vor dem Live-Kundenbetrieb, bezahlten Piloten oder der Produktion.',
        ],
        'bespoke_runtime' => [
            'label' => 'Maßgeschneidertes internes Tool, das für einen Kunden betrieben wird',
            'description' => 'Ein dokumentiertes Arbeitsverhältnis; Sie betreiben es und der Client erhält nur Login/API-Zugriff.',
        ],
        'bespoke_client' => [
            'label' => 'Maßgeschneiderte Anwendung im Besitz des Kunden',
            'description' => 'Der Kunde erhält das offizielle Paket im Rahmen seiner eigenen Rechte; Sie arbeiten als autorisierter Auftragnehmer.',
        ],
        'hosted' => [
            'label' => 'Kommerziell gehostetes Produkt / SaaS',
            'description' => 'Ein monetarisierter oder Freemium-Softwaredienst. Zu diesem Bereich gehört auch ein Bildschirm nur für Mitarbeiter, der diesen Dienst konfiguriert.',
        ],
        'distribution' => [
            'label' => 'Kundeninstallationen oder Paket-/Quellenlieferung',
            'description' => 'Ihr eigenes Produkt auf dem Server/der Cloud eines Kunden, in Containern oder einer pakethaltigen Quelle. Ausgenommen ist die oben genannte echte maßgeschneiderte Direkt-Client-Route.',
        ],
        'kits' => [
            'label' => 'Wiederverwendbarer Starter, Vorlage oder Entwicklerkit',
            'description' => 'Ein wiederverwendbares Produkt, das auf FLEX Fields basiert, auch wenn Käufer die offizielle Abhängigkeit selbst installieren.',
        ],
        'oem' => [
            'label' => 'OEM, Unterlizenzierung oder unabhängiger Weiterverkauf',
            'description' => 'Ein anderes Unternehmen kann Ihr softwarebasiertes Produkt installieren, unabhängig betreiben, weiterverbreiten oder unterlizenzieren.',
        ],
    ],
    'conditions' => [
        'free_scope' => [
            'label' => 'Der persönliche/interne Gebrauch entspricht §1.7(a)',
            'description' => 'Die Anwendung selbst nicht als kommerzielles Softwareprodukt bereitstellen; Hinweise beibehalten und die eingeräumten Rechte beachten.',
        ],
        'evaluation_private' => [
            'label' => 'Die Evaluierung erfolgt privat und nicht produktionstechnisch',
            'description' => 'Kein Live-Kundenbetrieb, bezahlter Pilot oder Produktionsstart im Rahmen der Evaluierungsausnahme.',
        ],
        'bespoke_runtime_scope' => [
            'label' => 'Das Nur-Laufzeit-Engagement erfüllt §1.7(b)',
            'description' => 'Identifizierter Kunde und schriftlicher maßgeschneiderter Umfang; Sie bleiben der verantwortliche Betreiber; Interne Nutzung, kein Weiterverkauf der Client-Software oder Paketlieferung.',
        ],
        'direct_client' => [
            'label' => 'Die Direkt-Client-Route entspricht §5.6',
            'description' => 'Echte Maßarbeit; Der Kunde erhält direkt die offizielle Abhängigkeit und autorisiert Sie. Sie stellen keine eigene Paketkopie/Private Fork oder ein wiederverwendbares Produkt bereit.',
        ],
        'client_free_scope' => [
            'label' => 'Der Kunde qualifiziert sich selbstständig für die kostenfreie interne Nutzung',
            'description' => 'Der Kunde erfüllt §1.7(a) aus eigenem Recht. Es nutzt die Anwendung für seinen Betrieb, anstatt anderen einen Softwaredienst anzubieten.',
        ],
        'hosted_scope' => [
            'label' => 'Gewerbliche Kunden erhalten nur Laufzeitzugriff',
            'description' => 'Für jede ausgewählte gehostete Nutzung ist der Lizenznehmer des Produkts Eigentümer und Betreiber des Produkts, direkt oder durch autorisiertes Personal. Kunden erhalten nur Laufzeitzugriff, ohne vom Kunden kontrollierte Installation, Paket-/Quelllieferung oder unabhängige Wiederverkäuferrechte.',
        ],
    ],
    'ownership' => [
        'one' => [
            'label' => 'Eine natürliche oder juristische Person',
            'description' => 'Umfasst Abteilungen und Marken desselben Unternehmens; ausgenommen separat eingetragene Unternehmen.',
        ],
        'group' => [
            'label' => 'Muttergesellschaft + hundertprozentige Tochtergesellschaften',
            'description' => '100 % Eigentumsanteile und Stimmrechte über die Qualifizierungskette des genannten Mutterunternehmens.',
        ],
        'other' => [
            'label' => 'Andere Eigentümer/Betreiber',
            'description' => 'Teilweise im Besitz befindliche Unternehmen, Schwesterunternehmen, Joint Ventures oder unabhängige Kunden; bewerten ihre Lizenzen gesondert.',
        ],
    ],
    'products' => [
        'one' => [
            'label' => 'Ein Produkt',
            'description' => 'Versionen, Sprachen, Regionen und Mandanteninstanzen desselben Produkts sind enthalten.',
        ],
        'two' => [
            'label' => 'Zwei unabhängige Produkte',
            'description' => 'Separate Produktidentitäten statt Module oder Domänen derselben Anwendung.',
        ],
        'portfolio' => [
            'label' => 'Drei oder mehr / zukünftiges Portfolio',
            'description' => 'Mehrere unabhängig angebotene Produkte, die Eigentum des Lizenznehmers sind und von ihm betrieben werden.',
        ],
    ],
    'outcomes' => [
        'mixed' => [
            'title' => 'Unterschiedliche Verwendungszwecke erfordern unterschiedliche Abdeckungen',
            'detail' => 'Es gibt keine einzelne Empfehlung, die jede ausgewählte Aktivität abdeckt. Sehen Sie sich unten jedes Ergebnis an, einschließlich der Bedingungen, die noch bestätigt werden müssen.',
        ],
        'free' => [
            'title' => 'Kann zur kostenlosen Nutzung berechtigt sein',
            'detail' => 'Es ist kein kommerzieller Kauf erforderlich, wenn alle relevanten Bedingungen der kostenlosen Nutzung erfüllt sind. Es gelten weiterhin Beschränkungen und Hinweise zur Weiterverbreitung.',
        ],
        'single' => [
            'title' => 'Single Product · $249',
            'detail' => 'Ein benanntes Produkt und ein Lizenznehmer. Einmalige Standardlizenz mit unbegrenzt vielen Benutzern, Mandanten, Entwicklern und Umgebungen innerhalb des vereinbarten Geltungsbereichs.',
        ],
        'two_single' => [
            'title' => 'Zwei Single Product-Lizenzen · 498 $',
            'detail' => 'Zwei Singles kosten zu den aktuellen Listenpreisen weniger. Unlimited Company (699 $) ist eine Alternative, die das aktuelle und zukünftige Portfolio desselben Unternehmens abdeckt.',
        ],
        'unlimited' => [
            'title' => 'Unlimited Company · $699',
            'detail' => 'Die berechtigten aktuellen und zukünftigen gehosteten Produkte eines benannten Unternehmens. Bei drei Produkten kostet es weniger als bei drei Singles; Separate Singles bleiben eine Option.',
        ],
        'enterprise' => [
            'title' => 'Enterprise Group · $1,499',
            'detail' => 'Eine gemeinsame Lizenz für die benannte Muttergesellschaft und ihre hundertprozentigen Tochtergesellschaften. Geeignete separate Lizenzen können eine Alternative sein; der Gruppenumfang umfasst keine OEM- oder Kundenlieferung.',
        ],
        'separate' => [
            'title' => 'Separate Lizenzen oder vereinbarter Geltungsbereich',
            'detail' => 'Für diese Unternehmen gilt keine automatische gemeinsame Lizenz. Geeignete Single Product- oder Unlimited Company-Lizenzen je Eigentümer oder ein ausdrücklich vereinbarter Geltungsbereich können passen. Teilen Sie uns die Eigentumsstruktur mit.',
        ],
        'custom' => [
            'title' => 'Kundenspezifisch / OEM · individuelles Angebot',
            'detail' => 'Die Angebote beginnen bei 2.999 $ für einen eng spezifizierten Umfang, nicht für unbegrenzte Rechte oder eine feste Gebühr. Standardpläne genehmigen diese Aktivität nicht.',
        ],
        'review' => [
            'title' => 'Bestätigen Sie den Umfang, bevor Sie fortfahren',
            'detail' => 'Eine fehlende oder mehrdeutige Bedingung verhindert eine kostenlose Nutzung oder eine Standardempfehlung. Kontaktieren Sie den Autor mit dem Eigentums- und Liefermodell.',
        ],
        'incomplete' => [
            'title' => 'Es sind noch ein paar weitere Informationen nötig',
            'detail' => 'Wählen Sie Ihren Verwendungszweck und die entsprechenden Eigentums-/Produktoptionen aus.',
        ],
    ],
    'reasons' => [
        'mixed_uses' => 'Ihre Auswahl kombiniert Aktivitäten mit unterschiedlichen Lizenzierungsrouten oder unbestätigten Bedingungen.',
        'combined_uses' => 'Jede ausgewählte Nutzung wurde separat bewertet; Die folgenden Ergebnisse haben dieselbe Lizenzkategorie.',
        'mixed_owners' => 'Ihre eigenen gehosteten Produkte und kundeneigene gehostete Produkte benötigen jeweils eine angemessene Lizenz. Eine Agenturlizenz deckt nicht beide Eigentümer ab.',
        'client_distribution' => 'Für die Weitergabe an eigene Kunden benötigt der Kunde ausdrücklich vereinbarte individuelle Rechte. Die Ausnahme für maßgeschneiderte Agenturleistungen gewährt diese Rechte nicht.',
        'client_free_scope' => 'Bestätigen Sie, dass der Kunde unabhängig alle geltenden Bedingungen für die interne Nutzung erfüllt.',
        'choose_use' => 'Wählen Sie mindestens einen Verwendungszweck.',
        'choose_products' => 'Wählen Sie die Anzahl der unabhängigen Produkte.',
        'choose_ownership' => 'Identifizieren Sie den Eigentümer/Betreiber.',
        'distribution' => 'Kundenkopien, wiederverwendbare Kits und unabhängige OEM-/Reseller-Rechte bedürfen eines ausdrücklich vereinbarten Vertriebsumfangs.',
        'direct_client' => 'Wählen Sie die Nutzung durch den Kunden und bestätigen Sie die echten maßgeschneiderten Direktkundenbedingungen. Die Lizenz einer Agentur kann die Rechte des Kunden nicht ersetzen.',
        'hosted_scope' => 'Bestätigen Sie den Produktbesitz, das vom Anbieter kontrollierte Hosting und den Nur-Laufzeit-Kundenzugriff. Die kundengesteuerte Lieferung erfolgt auf einem anderen Weg.',
        'group' => 'Die ausgewählten kommerziellen Produkte umfassen eine qualifizierte hundertprozentige Unternehmensgruppe.',
        'other_entities' => 'Die ausgewählten Eigentümer/Betreiber gehören nicht zu einem Unternehmen und der qualifizierten hundertprozentigen Gruppe.',
        'one_product' => 'Die ausgewählte Verwendung ist ein kommerziell gehostetes Produkt, das einem Lizenznehmer gehört/von diesem betrieben wird.',
        'two_products' => 'Sie haben zwei unabhängige gehostete kommerzielle Produkte desselben Lizenznehmers ausgewählt.',
        'portfolio' => 'Sie haben drei oder mehr Produkte oder Portfolioabdeckung für zukünftige Produkte ausgewählt.',
        'evaluation' => 'Die Evaluierung ist nur vor der Produktion, dem Live-Kundenbetrieb und bezahlten Piloten kostenlos.',
        'bespoke_runtime' => 'Die dokumentierten Engagement-, internen Nutzungs- und Nur-Laufzeit-Zugriffsbedingungen müssen alle eingehalten werden.',
        'free_scope' => 'Bestätigen Sie, dass es sich bei der Anwendung um qualifizierte persönliche/interne Vorgänge handelt und nicht um ein Softwareprodukt, das an andere geliefert wird.',
        'client_free' => 'Eine wirklich maßgeschneiderte, kundeneigene interne Anwendung kann unter der Berechtigung des Kunden zur kostenlosen Nutzung in Frage kommen.',
        'permitted_free' => 'Ihre ausgewählte Nutzung und die bestätigten Konditionen passen zu den zusammengefassten Freinutzungsrouten.',
    ],
];
