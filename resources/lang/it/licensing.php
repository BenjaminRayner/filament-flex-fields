<?php

declare(strict_types=1);

return [
    'title' => 'Guida alla licenza',
    'license_label' => 'Licenza',
    'scope_internal' => 'Uso personale e interno · Gratuito',
    'scope_hosted' => 'SaaS commerciale · Standard',
    'scope_custom' => 'Distribuzione · Personalizzata',
    'notice_title' => 'La licenza giusta per il tuo progetto.',
    'notice' => 'L\'uso personale e gli strumenti interni qualificanti sono gratuiti. Il SaaS commerciale necessita di una licenza Standard; le installazioni dei clienti, i kit riutilizzabili e gli OEM necessitano di diritti personalizzati concordati.',
    'check_scope' => 'Trova la tua licenza',
    'intro' => 'Scegli come intendi utilizzare i FLEX Fields. Ti aiuteremo a identificare l\'ambito descritto nella LICENSE v2.0.',
    'selection_hint' => 'Selezionare ogni destinazione d\'uso e confermarne le condizioni. Valutiamo ogni utilizzo separatamente, compresi i tuoi prodotti e quelli di un cliente. Il conteggio dei prodotti segue identità di prodotto indipendenti, non utenti, domini o server.',
    'assess' => 'Controlla il mio utilizzo',
    'assessing' => 'Controllo…',
    'result' => 'Guida alla licenza',
    'contact' => 'Discuti ambito e acquisto',
    'plans' => 'Piani e acquisti',
    'client_note' => 'Per le applicazioni personalizzate di proprietà del cliente, la licenza appartiene al cliente. La licenza del portafoglio di un’agenzia non copre i suoi clienti.',
    'terms_note' => 'Questa guida riassume LICENSE v2.0 e COMMERCIAL.md; non rilascia né attiva alcuna licenza. Valgono i termini completi, la Conferma della licenza e qualsiasi ambito personalizzato concordato. I prezzi in USD escludono le tasse applicabili; la portata è confermata prima del pagamento.',
    'existing_note' => 'Hai già una licenza? I diritti esistenti mantengono la versione e l’ambito concordati. Questa guida non determina se una licenza precedente o personalizzata copre il tuo utilizzo.',
    'breakdown' => 'Valutazione in base all\'uso',
    'mixed_note' => 'Ogni risultato si applica solo all\'uso mostrato accanto. Un requisito personalizzato per un progetto non sostituisce né espande i diritti necessari per un altro.',
    'client_use' => [
        'internal' => [
            'label' => 'Operazioni interne del cliente',
            'description' => 'Un\'applicazione su misura per le operazioni del cliente, non un prodotto software fornito ad altri.',
        ],
        'hosted' => [
            'label' => 'Prodotto commerciale ospitato dal cliente',
            'description' => 'Il cliente possiede il suo SaaS e i clienti ricevono solo l\'accesso runtime. Il client necessita della propria licenza adeguata.',
        ],
        'distribution' => [
            'label' => 'Il client distribuisce software o installazioni del cliente',
            'description' => 'Il cliente fornisce ai propri clienti copie contenenti pacchetti, kit riutilizzabili, installazioni o diritti OEM indipendenti.',
        ],
    ],
    'questions' => [
        'client_use' => 'Come utilizzerà la sua applicazione il cliente?',
        'client_ownership' => 'Chi possiede e gestisce i prodotti ospitati dal cliente?',
        'client_products' => 'Quanti prodotti ospitati possiede il cliente?',
        'uses' => 'Per cosa utilizzerai FLEX?',
        'conditions' => 'Confermare le relative condizioni',
        'ownership' => 'Chi possiede e gestisce i tuoi prodotti ospitati?',
        'products' => 'Quanti prodotti ospitati possiedi?',
    ],
    'uses' => [
        'personal' => [
            'label' => 'Uso personale, didattico o di ricerca',
            'description' => 'Un progetto privato, di apprendimento o di ricerca, piuttosto che la gestione di un prodotto software commerciale.',
        ],
        'internal' => [
            'label' => 'Le mie attività commerciali',
            'description' => 'Il CRM del tuo personale, il negozio, le prenotazioni o l\'amministrazione aziendale. Un back office è qualificato solo quando amministra l\'attività indipendentemente da un servizio software venduto.',
        ],
        'evaluation' => [
            'label' => 'Sviluppo e valutazione privati',
            'description' => 'Testare un potenziale prodotto prima delle operazioni dal vivo del cliente, dei progetti pilota pagati o della produzione.',
        ],
        'bespoke_runtime' => [
            'label' => 'Strumento interno su misura gestito per un cliente',
            'description' => 'Un incarico su commissione documentato; lo gestisci tu e il cliente riceve solo accesso tramite login o API.',
        ],
        'bespoke_client' => [
            'label' => 'Applicazione personalizzata di proprietà del cliente',
            'description' => 'Il cliente ottiene il pacchetto ufficiale secondo i propri diritti; lavori come appaltatore autorizzato.',
        ],
        'hosted' => [
            'label' => 'Prodotto ospitato commerciale / SaaS',
            'description' => 'Un servizio software monetizzato o freemium. Appartiene a questo ambito anche una schermata riservata al personale che configura tale servizio.',
        ],
        'distribution' => [
            'label' => 'Installazioni del cliente o consegna del pacchetto/origine',
            'description' => 'Il tuo prodotto sul server/cloud, sui contenitori o sulla fonte contenente il pacchetto di un cliente. Esclude il percorso autentico e personalizzato del cliente diretto di cui sopra.',
        ],
        'kits' => [
            'label' => 'Starter, modello o kit di sviluppo riutilizzabile',
            'description' => 'Un prodotto riutilizzabile costruito attorno a FLEX Fields, anche se gli acquirenti installano essi stessi la dipendenza ufficiale.',
        ],
        'oem' => [
            'label' => 'OEM, sublicenza o rivendita indipendente',
            'description' => 'Un\'altra azienda può installare, gestire in modo indipendente, ridistribuire o concedere in sublicenza il tuo prodotto basato su software.',
        ],
    ],
    'conditions' => [
        'free_scope' => [
            'label' => 'L\'uso personale/interno è conforme al §1.7(a)',
            'description' => 'Non fornire l’applicazione stessa come prodotto software commerciale; conserva gli avvisi e rispetta i diritti concessi.',
        ],
        'evaluation_private' => [
            'label' => 'La valutazione è privata e non produttiva',
            'description' => 'Nessuna operazione con il cliente dal vivo, pilota a pagamento o lancio di produzione nell\'ambito dell\'eccezione di valutazione.',
        ],
        'bespoke_runtime_scope' => [
            'label' => 'L\'impegno solo runtime soddisfa §1.7(b)',
            'description' => 'Cliente identificato e ambito personalizzato scritto; rimani operatore responsabile; uso interno, nessuna rivendita di software client o consegna di pacchetti.',
        ],
        'direct_client' => [
            'label' => 'Il percorso cliente diretto soddisfa il §5.6',
            'description' => 'Veri lavori su misura; client ottiene direttamente la dipendenza ufficiale e ti autorizza. Non fornire la copia del pacchetto/il fork privato o un prodotto riutilizzabile.',
        ],
        'client_free_scope' => [
            'label' => 'Il cliente soddisfa autonomamente i requisiti per l’uso interno gratuito',
            'description' => 'Il cliente soddisfa a pieno titolo il §1.7(a). Utilizza l\'applicazione per le sue operazioni, anziché fornire un servizio software ad altri.',
        ],
        'hosted_scope' => [
            'label' => 'I clienti commerciali ricevono solo l\'accesso runtime',
            'description' => 'Per ogni utilizzo ospitato selezionato, il licenziatario del prodotto lo possiede e lo gestisce, direttamente o tramite personale autorizzato. I clienti ottengono solo l\'accesso runtime, senza installazione controllata dal cliente, consegna di pacchetti/sorgenti o diritti di rivenditore indipendente.',
        ],
    ],
    'ownership' => [
        'one' => [
            'label' => 'Una persona fisica o giuridica',
            'description' => 'Include dipartimenti e marchi della stessa entità; sono escluse le società costituite separatamente.',
        ],
        'group' => [
            'label' => 'Capogruppo + società controllate al 100%.',
            'description' => 'Partecipazione al 100% e diritti di voto attraverso la catena di qualificazione della società madre nominata.',
        ],
        'other' => [
            'label' => 'Altri proprietari/gestori',
            'description' => 'Società partecipate, consociate, joint venture o clienti non collegati; valutare separatamente le loro licenze.',
        ],
    ],
    'products' => [
        'one' => [
            'label' => 'Un prodotto',
            'description' => 'Sono incluse versioni, lingue, regioni e istanze tenant dello stesso prodotto.',
        ],
        'two' => [
            'label' => 'Due prodotti indipendenti',
            'description' => 'Identità di prodotto separate, anziché moduli o domini della stessa applicazione.',
        ],
        'portfolio' => [
            'label' => 'Tre o più / portafoglio futuro',
            'description' => 'Prodotti multipli offerti in modo indipendente di proprietà e gestiti dal licenziatario.',
        ],
    ],
    'outcomes' => [
        'mixed' => [
            'title' => 'Usi diversi necessitano di coperture diverse',
            'detail' => 'Non esiste un\'unica raccomandazione che copra ogni attività selezionata. Esamina ciascun risultato di seguito, comprese le condizioni che richiedono ancora conferma.',
        ],
        'free' => [
            'title' => 'Può beneficiare dell\'uso gratuito',
            'detail' => 'Non è richiesto alcun acquisto commerciale quando valgono tutte le condizioni di utilizzo gratuito pertinenti. Si applicano ancora le restrizioni e gli avvisi di ridistribuzione.',
        ],
        'single' => [
            'title' => 'Single Product · $249',
            'detail' => 'Un prodotto designato e un licenziatario. Licenza Standard una tantum con utenti, tenant, sviluppatori e ambienti illimitati nell’ambito concordato.',
        ],
        'two_single' => [
            'title' => 'Due licenze Single Product · $498',
            'detail' => 'Due licenze Single Product costano meno ai prezzi attuali. Unlimited Company ($699) copre anche il portafoglio presente e futuro della stessa entità.',
        ],
        'unlimited' => [
            'title' => 'Unlimited Company · $699',
            'detail' => 'Prodotti ospitati attuali e futuri idonei di un’entità designata. Da tre prodotti costa meno di tre licenze Single Product; restano possibili licenze separate.',
        ],
        'enterprise' => [
            'title' => 'Enterprise Group · $1,499',
            'detail' => 'Una licenza comune per la capogruppo designata e le controllate al 100%. Licenze separate adeguate possono essere un’alternativa; l’ambito del gruppo non include OEM o consegna al cliente.',
        ],
        'separate' => [
            'title' => 'Licenze separate o copertura concordata',
            'detail' => 'Queste entità non condividono automaticamente una licenza. Possono essere adatte licenze Single Product o Unlimited Company per ciascun proprietario, oppure una copertura espressamente concordata. Contattaci indicando la struttura di proprietà.',
        ],
        'custom' => [
            'title' => 'Personalizzato/OEM · quotato individualmente',
            'detail' => 'Le quotazioni partono da $ 2.999 per un ambito strettamente specificato, non per diritti illimitati o una tariffa fissa. I piani standard non autorizzano questa attività.',
        ],
        'review' => [
            'title' => 'Confermare l\'ambito prima di procedere',
            'detail' => 'Una condizione mancante o ambigua impedisce un utilizzo gratuito o una raccomandazione standard. Contatta l\'autore con la proprietà e il modello di consegna.',
        ],
        'incomplete' => [
            'title' => 'Serve qualche informazione in più',
            'detail' => 'Seleziona la destinazione d\'uso e le relative opzioni proprietà/prodotto.',
        ],
    ],
    'reasons' => [
        'mixed_uses' => 'La tua selezione combina attività con diversi percorsi di licenza o condizioni non confermate.',
        'combined_uses' => 'Ogni utilizzo selezionato è stato valutato separatamente; i risultati seguenti condividono la stessa categoria di licenza.',
        'mixed_owners' => 'I tuoi prodotti ospitati e quelli di proprietà del cliente necessitano di una copertura adeguata e separata. Una licenza di agenzia non copre entrambi i proprietari.',
        'client_distribution' => 'Il cliente necessita di diritti specifici espressamente concordati per distribuire il software ai propri clienti. L’eccezione per il lavoro su misura di un’agenzia non concede tali diritti.',
        'client_free_scope' => 'Confermare che il cliente soddisfi in modo indipendente tutte le condizioni di uso interno applicabili.',
        'choose_use' => 'Scegli almeno una destinazione d\'uso.',
        'choose_products' => 'Scegli il numero di prodotti indipendenti.',
        'choose_ownership' => 'Identificare il proprietario/gestore.',
        'distribution' => 'Le copie del cliente, i kit riutilizzabili e i diritti di OEM/rivenditore indipendenti necessitano di un ambito di distribuzione espressamente concordato.',
        'direct_client' => 'Scegli l\'uso del cliente e conferma le condizioni reali e personalizzate per il cliente diretto. La licenza di un’agenzia non può sostituire i diritti del cliente.',
        'hosted_scope' => 'Conferma la proprietà del prodotto, l\'hosting controllato dal fornitore e l\'accesso del cliente solo runtime. La consegna controllata dal cliente segue un percorso diverso.',
        'group' => 'I prodotti commerciali selezionati appartengono ad un gruppo societario qualificato interamente posseduto.',
        'other_entities' => 'I proprietari/operatori selezionati sono esterni a un\'entità e al gruppo interamente controllato qualificato.',
        'one_product' => 'L\'utilizzo selezionato è un prodotto commerciale ospitato di proprietà/gestito da un licenziatario.',
        'two_products' => 'Hai selezionato due prodotti commerciali ospitati indipendenti dello stesso licenziatario.',
        'portfolio' => 'Hai selezionato tre o più prodotti o copertura del portafoglio per prodotti futuri.',
        'evaluation' => 'La valutazione è gratuita solo prima della produzione, delle operazioni dal vivo con i clienti e dei progetti pilota a pagamento.',
        'bespoke_runtime' => 'Devono essere rispettate le condizioni di impegno documentato, uso interno e accesso solo runtime.',
        'free_scope' => 'Conferma che l’applicazione serva a operazioni personali o interne ammesse, e non sia un prodotto software fornito ad altri.',
        'client_free' => 'Un\'applicazione interna autentica e personalizzata di proprietà del cliente può beneficiare del diritto di utilizzo gratuito del cliente.',
        'permitted_free' => 'L\'utilizzo selezionato e le condizioni confermate si adattano ai percorsi di utilizzo gratuito riepilogati.',
    ],
];
