<?php

declare(strict_types=1);

return [
    'title' => 'Licentiegids',
    'license_label' => 'Licenties',
    'scope_internal' => 'Persoonlijk en intern gebruik · Gratis',
    'scope_hosted' => 'Commerciële SaaS · Standaard',
    'scope_custom' => 'Distributie · Aangepast',
    'notice_title' => 'De juiste licentie voor uw project.',
    'notice' => 'Persoonlijk gebruik en daarvoor in aanmerking komende interne tools zijn gratis. Voor commerciële SaaS is een Standard-licentie nodig; klantinstallaties, herbruikbare kits en OEM vereisen overeengekomen specifieke rechten.',
    'check_scope' => 'Vind uw licentie',
    'intro' => 'Kies hoe u FLEX Fields wilt gebruiken. Wij helpen u de reikwijdte te identificeren die wordt beschreven in LICENSE v2.0.',
    'selection_hint' => 'Selecteer elk beoogd gebruik en bevestig de voorwaarden ervan. Wij beoordelen elk gebruik afzonderlijk, dus ook uw producten en de producten van een klant. Het aantal producten volgt onafhankelijke productidentiteiten, niet gebruikers, domeinen of servers.',
    'assess' => 'Controleer mijn gebruik',
    'assessing' => 'Controleren…',
    'result' => 'Uw licentiegids',
    'contact' => 'Bespreek omvang & aanschaf',
    'plans' => 'Plannen & inkoop',
    'client_note' => 'Voor op maat gemaakte applicaties die eigendom zijn van de klant, behoort de licentie toe aan de klant. De portefeuillelicentie van een bureau dekt zijn klanten niet.',
    'terms_note' => 'Deze handleiding geeft een samenvatting van LICENSE v2.0 en COMMERCIAL.md; er wordt geen licentie afgegeven of geactiveerd. De volledige voorwaarden, uw Licentiebevestiging en eventuele overeengekomen Aangepaste reikwijdte zijn van toepassing. USD-prijzen zijn exclusief toepasselijke belastingen; bereik is bevestigd vóór betaling.',
    'existing_note' => 'Heeft u al een licentie? Bestaande licenties behouden hun overeengekomen versie en reikwijdte. Deze gids bepaalt niet of een eerdere of op maat gemaakte licentie uw gebruik dekt.',
    'breakdown' => 'Beoordeling door gebruik',
    'mixed_note' => 'Elk resultaat is alleen van toepassing op het gebruik dat ernaast wordt weergegeven. Een aangepaste vereiste voor het ene project vervangt of breidt niet de rechten uit die nodig zijn voor een ander project.',
    'client_use' => [
        'internal' => [
            'label' => 'Interne bedrijfsvoering van de klant',
            'description' => 'Een op maat gemaakte applicatie voor de eigen activiteiten van die klant, niet een softwareproduct dat hij aan anderen levert.',
        ],
        'hosted' => [
            'label' => 'Commercieel gehost product van de klant',
            'description' => 'De klant is eigenaar van zijn SaaS en klanten krijgen alleen runtime-toegang. De klant heeft een eigen geschikte licentie nodig.',
        ],
        'distribution' => [
            'label' => 'Klant distribueert software of klantinstallaties',
            'description' => 'De opdrachtgever levert pakketbevattende kopieën, herbruikbare kits, installaties of onafhankelijke OEM-rechten aan zijn eigen klanten.',
        ],
    ],
    'questions' => [
        'client_use' => 'Hoe gaat de klant zijn applicatie gebruiken?',
        'client_ownership' => 'Wie is eigenaar en beheerder van de gehoste producten van de klant?',
        'client_products' => 'Hoeveel gehoste producten bezit de klant?',
        'uses' => 'Waar ga je FLEX voor gebruiken?',
        'conditions' => 'Bevestig de relevante voorwaarden',
        'ownership' => 'Wie is eigenaar en beheerder van uw gehoste producten?',
        'products' => 'Hoeveel gehoste producten bezit u?',
    ],
    'uses' => [
        'personal' => [
            'label' => 'Persoonlijk, educatief of onderzoeksgebruik',
            'description' => 'Een privéproject, leren of onderzoeken, in plaats van een commercieel softwareproduct te exploiteren.',
        ],
        'internal' => [
            'label' => 'Mijn eigen bedrijfsvoering',
            'description' => 'Uw personeels-CRM, winkel, boekingen of bedrijfsadministratie. Een backoffice komt alleen in aanmerking als deze de bedrijfsvoering onafhankelijk van de softwaredienst die u verkoopt, beheert.',
        ],
        'evaluation' => [
            'label' => 'Particuliere ontwikkeling & evaluatie',
            'description' => 'Een toekomstig product testen vóór live klantactiviteiten, betaalde pilots of productie.',
        ],
        'bespoke_runtime' => [
            'label' => 'Op maat gemaakte interne tool voor een klant',
            'description' => 'Een gedocumenteerde opdracht op maat; u beheert de toepassing en de klant krijgt alleen toegang via login of API.',
        ],
        'bespoke_client' => [
            'label' => 'Op maat gemaakte applicatie, eigendom van de klant',
            'description' => 'De klant verkrijgt het officiële pakket onder zijn eigen rechten; u werkt als haar erkende opdrachtnemer.',
        ],
        'hosted' => [
            'label' => 'Commercieel gehost product / SaaS',
            'description' => 'Een softwareservice waarmee inkomsten worden gegenereerd of freemium. Ook een staff-only scherm dat die dienst configureert behoort tot deze scope.',
        ],
        'distribution' => [
            'label' => 'Klantinstallaties of pakket-/bronlevering',
            'description' => 'Uw eigen product op de server/cloud, containers of pakkethoudende bron van een klant. Exclusief de echte, op maat gemaakte directe klantroute hierboven.',
        ],
        'kits' => [
            'label' => 'Herbruikbare starter-, sjabloon- of ontwikkelaarskit',
            'description' => 'Een herbruikbaar product gebouwd rond FLEX Fields, zelfs als kopers de officiële afhankelijkheid zelf installeren.',
        ],
        'oem' => [
            'label' => 'OEM, sublicentie of onafhankelijke wederverkoop',
            'description' => 'Een ander bedrijf kan uw op software gebaseerde product installeren, zelfstandig exploiteren, herdistribueren of in sublicentie geven.',
        ],
    ],
    'conditions' => [
        'free_scope' => [
            'label' => 'Persoonlijk/intern gebruik voldoet aan §1.7(a)',
            'description' => 'Lever de applicatie zelf niet als commercieel softwareproduct; behoud de vermeldingen en blijf binnen de verleende rechten.',
        ],
        'evaluation_private' => [
            'label' => 'Evaluatie is privé en geen productie',
            'description' => 'Geen live klantbewerkingen, betaalde pilot of productielancering onder de evaluatie-uitzondering.',
        ],
        'bespoke_runtime_scope' => [
            'label' => 'De runtime-only-betrokkenheid voldoet aan §1.7(b)',
            'description' => 'Geïdentificeerde klant en schriftelijke op maat gemaakte scope; jij blijft verantwoordelijke uitvoerder; intern gebruik, geen wederverkoop van clientsoftware of levering van pakketten.',
        ],
        'direct_client' => [
            'label' => 'De directe-clientroute voldoet aan §5.6',
            'description' => 'Echt maatwerk; de klant verkrijgt rechtstreeks de officiële afhankelijkheid en machtigt u. U levert geen eigen pakketkopie, private fork of herbruikbaar product.',
        ],
        'client_free_scope' => [
            'label' => 'De opdrachtgever komt zelfstandig in aanmerking voor gratis intern gebruik',
            'description' => 'De cliënt voldoet op zichzelf aan §1.7(a). Het gebruikt de applicatie voor zijn activiteiten, in plaats van een softwaredienst aan anderen te leveren.',
        ],
        'hosted_scope' => [
            'label' => 'Commerciële klanten krijgen alleen runtime-toegang',
            'description' => 'Voor elk geselecteerd gehost gebruik is de licentiehouder van het product eigenaar en exploitant van het product, rechtstreeks of via geautoriseerd personeel. Klanten krijgen alleen runtime-toegang, zonder door de klant gecontroleerde installatie, pakket-/bronlevering of onafhankelijke wederverkoperrechten.',
        ],
    ],
    'ownership' => [
        'one' => [
            'label' => 'Eén persoon of rechtspersoon',
            'description' => 'Omvat afdelingen en merken van dezelfde entiteit; sluit afzonderlijk opgerichte vennootschappen uit.',
        ],
        'group' => [
            'label' => 'Moedermaatschappij + volledige dochterondernemingen',
            'description' => '100% eigendomsbelang en stemrechten via de kwalificerende keten van de genoemde moedermaatschappij.',
        ],
        'other' => [
            'label' => 'Andere eigenaren/exploitanten',
            'description' => 'Gedeeltelijk eigen bedrijven, zusterbedrijven, joint ventures of niet-verbonden klanten; beoordelen hun licenties afzonderlijk.',
        ],
    ],
    'products' => [
        'one' => [
            'label' => 'Eén product',
            'description' => 'Versies, talen, regio\'s en tenantinstanties van hetzelfde product zijn inbegrepen.',
        ],
        'two' => [
            'label' => 'Twee onafhankelijke producten',
            'description' => 'Afzonderlijke productidentiteiten, in plaats van modules of domeinen van dezelfde applicatie.',
        ],
        'portfolio' => [
            'label' => 'Drie of meer / toekomstige portefeuille',
            'description' => 'Meerdere onafhankelijk aangeboden producten die eigendom zijn van en beheerd worden door de licentiehouder.',
        ],
    ],
    'outcomes' => [
        'mixed' => [
            'title' => 'Verschillende toepassingen vereisen een verschillende dekking',
            'detail' => 'Er is geen enkele aanbeveling die elke geselecteerde activiteit bestrijkt. Bekijk hieronder elk resultaat, inclusief de omstandigheden die nog bevestiging vereisen.',
        ],
        'free' => [
            'title' => 'Komt mogelijk in aanmerking voor gratis gebruik',
            'detail' => 'Er is geen commerciële aankoop vereist als aan alle relevante voorwaarden voor gratis gebruik wordt voldaan. Beperkingen en mededelingen voor herdistributie zijn nog steeds van toepassing.',
        ],
        'single' => [
            'title' => 'Single Product · $249',
            'detail' => 'Eén benoemd product en één licentiehouder. Eenmalige Standard-licentie met onbeperkte gebruikers, tenants, ontwikkelaars en omgevingen binnen de overeengekomen reikwijdte.',
        ],
        'two_single' => [
            'title' => 'Twee Single Product-licenties · $498',
            'detail' => 'Twee Single Product-licenties kosten minder tegen de huidige prijzen. Unlimited Company ($699) dekt ook de huidige en toekomstige portefeuille van dezelfde entiteit.',
        ],
        'unlimited' => [
            'title' => 'Unlimited Company · $699',
            'detail' => 'De in aanmerking komende huidige en toekomstige gehoste producten van één genoemde entiteit. Bij drie producten kost het minder dan drie Singles; losse Singles blijven een optie.',
        ],
        'enterprise' => [
            'title' => 'Enterprise Group · $1,499',
            'detail' => 'Een gezamenlijke licentie voor de benoemde moedermaatschappij en volledige dochterondernemingen. Passende afzonderlijke licenties kunnen een alternatief zijn; de groepsdekking omvat geen OEM of levering aan klanten.',
        ],
        'separate' => [
            'title' => 'Afzonderlijke licenties of overeengekomen dekking',
            'detail' => 'Voor deze entiteiten geldt geen automatische gedeelde licentie. Passende Single Product- of Unlimited Company-licenties per eigenaar, of uitdrukkelijk overeengekomen dekking, kunnen geschikt zijn. Neem contact op met de eigendomsstructuur.',
        ],
        'custom' => [
            'title' => 'Op maat / OEM · individueel geofferd',
            'detail' => 'Offertes beginnen bij $ 2.999 voor een nauwkeurig gespecificeerde reikwijdte, niet voor onbeperkte rechten of een vast tarief. Standaardplannen staan deze activiteit niet toe.',
        ],
        'review' => [
            'title' => 'Bevestig het bereik voordat u doorgaat',
            'detail' => 'Een ontbrekende of dubbelzinnige voorwaarde verhindert een gratis gebruiks- of standaardaanbeveling. Neem contact op met de auteur over het eigendoms- en leveringsmodel.',
        ],
        'incomplete' => [
            'title' => 'Er is iets meer informatie nodig',
            'detail' => 'Selecteer uw beoogde gebruik en de relevante eigendoms-/productopties.',
        ],
    ],
    'reasons' => [
        'mixed_uses' => 'Uw selectie combineert activiteiten met verschillende licentieroutes of onbevestigde voorwaarden.',
        'combined_uses' => 'Ieder geselecteerd gebruik is afzonderlijk beoordeeld; de onderstaande resultaten delen dezelfde licentiecategorie.',
        'mixed_owners' => 'Uw eigen gehoste producten en gehoste producten die eigendom zijn van de klant hebben aparte adequate dekking nodig. Eén bureaulicentie dekt niet beide eigenaren.',
        'client_distribution' => 'De klant heeft uitdrukkelijk overeengekomen specifieke rechten nodig om software aan eigen klanten te distribueren. De uitzondering voor maatwerkbureaus verleent die rechten niet.',
        'client_free_scope' => 'Bevestig dat de cliënt zelfstandig voldoet aan alle toepasselijke voorwaarden voor intern gebruik.',
        'choose_use' => 'Kies minstens één beoogd gebruik.',
        'choose_products' => 'Kies het aantal onafhankelijke producten.',
        'choose_ownership' => 'Identificeer de eigenaar/operator.',
        'distribution' => 'Kopieën voor klanten, herbruikbare kits en onafhankelijke OEM/reseller-rechten vereisen een uitdrukkelijk overeengekomen distributieomvang.',
        'direct_client' => 'Kies het gebruik door de klant en bevestig de echte op maat gemaakte directe klantvoorwaarden. De licentie van een bureau kan de rechten van de klant niet vervangen.',
        'hosted_scope' => 'Bevestig producteigendom, door de leverancier gecontroleerde hosting en runtime-only klanttoegang. Klantgestuurde levering volgt een andere route.',
        'group' => 'De geselecteerde commerciële producten bestrijken een in aanmerking komende bedrijfsgroep die volledig eigendom is.',
        'other_entities' => 'De geselecteerde eigenaren/exploitanten vallen buiten één entiteit en de in aanmerking komende groep die volledig eigendom is.',
        'one_product' => 'Het geselecteerde gebruik is een commercieel gehost product dat eigendom is van/beheerd wordt door één licentiehouder.',
        'two_products' => 'U hebt twee onafhankelijk gehoste commerciële producten van dezelfde licentiehouder geselecteerd.',
        'portfolio' => 'U heeft drie of meer producten of portfoliodekking voor toekomstige producten geselecteerd.',
        'evaluation' => 'Evaluatie is alleen gratis vóór productie, live klantoperaties en betaalde pilots.',
        'bespoke_runtime' => 'De gedocumenteerde voorwaarden voor betrokkenheid, intern gebruik en runtime-only toegang moeten allemaal gelden.',
        'free_scope' => 'Bevestig dat de toepassing dient voor toegestaan persoonlijk of intern gebruik, en geen softwareproduct is dat aan anderen wordt geleverd.',
        'client_free' => 'Een echte, op maat gemaakte interne applicatie van de klant kan in aanmerking komen onder het recht op gratis gebruik van de klant zelf.',
        'permitted_free' => 'Uw geselecteerde gebruiks- en bevestigde voorwaarden passen bij de samengevatte gratis gebruiksroutes.',
    ],
];
