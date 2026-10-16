<?php

declare(strict_types=1);

return [
    'title' => 'Guide des licences',
    'license_label' => 'Licence',
    'scope_internal' => 'Usage personnel et interne · Gratuit',
    'scope_hosted' => 'SaaS commercial · Standard',
    'scope_custom' => 'Distribution · Personnalisé',
    'notice_title' => 'La bonne licence pour votre projet.',
    'notice' => 'L\'utilisation personnelle et les outils internes éligibles sont gratuits. Le SaaS commercial nécessite une licence standard ; Les installations client, les kits réutilisables et les OEM nécessitent des droits personnalisés convenus.',
    'check_scope' => 'Trouvez votre licence',
    'intro' => 'Choisissez comment utiliser FLEX Fields. Nous vous aidons à identifier le périmètre décrit dans LICENSE v2.0.',
    'selection_hint' => 'Sélectionnez chaque utilisation prévue et confirmez ses conditions. Nous évaluons chaque utilisation séparément, y compris vos produits et ceux d’un client. Le nombre de produits suit des identités de produits indépendantes, et non des utilisateurs, des domaines ou des serveurs.',
    'assess' => 'Vérifier mon utilisation',
    'assessing' => 'Vérification…',
    'result' => 'Votre guide de licence',
    'contact' => 'Discutez de la portée et de l\'achat',
    'plans' => 'Plans et achats',
    'client_note' => 'Pour les applications client sur mesure, la licence appartient au client. La licence de portefeuille d’une agence ne couvre pas ses clients.',
    'terms_note' => 'Ce guide résume LICENSE v2.0 et COMMERCIAL.md ; il ne délivre ni n\'active de licence. Les conditions complètes, votre confirmation de licence et toute portée personnalisée convenue prévalent. Les prix en USD excluent les taxes applicables ; la portée est confirmée avant le paiement.',
    'existing_note' => 'Vous possédez déjà une licence ? Les droits existants conservent leur version et leur périmètre convenus. Ce guide ne détermine pas si une licence antérieure ou personnalisée couvre votre utilisation.',
    'breakdown' => 'Bilan par l\'usage',
    'mixed_note' => 'Chaque résultat s\'applique uniquement à l\'utilisation indiquée à côté. Une exigence personnalisée pour un projet ne remplace ni n’étend les droits nécessaires pour un autre.',
    'client_use' => [
        'internal' => [
            'label' => 'Opérations internes du Client',
            'description' => 'Une application sur mesure pour les propres opérations de ce client, et non un produit logiciel qu\'il fournit à d\'autres.',
        ],
        'hosted' => [
            'label' => 'Produit hébergé commercialement par le client',
            'description' => 'Le client est propriétaire de son SaaS et les clients bénéficient uniquement d\'un accès à l\'exécution. Le client a besoin de sa propre licence appropriée.',
        ],
        'distribution' => [
            'label' => 'Le client distribue des logiciels ou des installations client',
            'description' => 'Le client fournit à ses propres clients des copies contenant des packages, des kits réutilisables, des installations ou des droits OEM indépendants.',
        ],
    ],
    'questions' => [
        'client_use' => 'Comment le client va-t-il utiliser son application ?',
        'client_ownership' => 'Qui possède et exploite les produits hébergés du client ?',
        'client_products' => 'Combien de produits hébergés le client possède-t-il ?',
        'uses' => 'Pour quoi utiliserez-vous FLEX ?',
        'conditions' => 'Confirmez les conditions pertinentes',
        'ownership' => 'Qui possède et exploite vos produits hébergés ?',
        'products' => 'Combien de produits hébergés possédez-vous ?',
    ],
    'uses' => [
        'personal' => [
            'label' => 'Utilisation personnelle, éducative ou de recherche',
            'description' => 'Un projet privé, d\'apprentissage ou de recherche, plutôt que l\'exploitation d\'un produit logiciel commercial.',
        ],
        'internal' => [
            'label' => 'Mes propres opérations commerciales',
            'description' => 'Votre CRM du personnel, votre boutique, vos réservations ou votre administration commerciale. Un back-office n’est admissible que lorsqu’il administre l’entreprise indépendamment d’un service logiciel que vous vendez.',
        ],
        'evaluation' => [
            'label' => 'Développement & évaluation privés',
            'description' => 'Tester un produit potentiel avant les opérations client en direct, les pilotes rémunérés ou la production.',
        ],
        'bespoke_runtime' => [
            'label' => 'Outil interne sur mesure exploité pour un client',
            'description' => 'Un engagement de travail contre rémunération documenté ; vous l\'utilisez et le client reçoit uniquement un accès de connexion/API.',
        ],
        'bespoke_client' => [
            'label' => 'Application sur mesure appartenant au client',
            'description' => 'Le client obtient le package officiel sous ses propres droits ; vous travaillez en tant qu\'entrepreneur autorisé.',
        ],
        'hosted' => [
            'label' => 'Produit hébergé commercial / SaaS',
            'description' => 'Un service logiciel monétisé ou freemium. Un écran réservé au personnel qui configure ce service appartient également à cette portée.',
        ],
        'distribution' => [
            'label' => 'Installations chez le client ou livraison du paquet ou du code source',
            'description' => 'Votre propre produit sur le serveur/cloud, les conteneurs ou la source contenant un package d\'un client. Exclut le véritable parcours client direct sur mesure ci-dessus.',
        ],
        'kits' => [
            'label' => 'Kit de démarrage, modèle ou développeur réutilisable',
            'description' => 'Un produit réutilisable construit autour de FLEX Fields, même si les acheteurs installent eux-mêmes la dépendance officielle.',
        ],
        'oem' => [
            'label' => 'OEM, sous-licence ou revente indépendante',
            'description' => 'Une autre entreprise peut installer, exploiter de manière indépendante, redistribuer ou accorder une sous-licence à votre produit logiciel.',
        ],
    ],
    'conditions' => [
        'free_scope' => [
            'label' => 'L\'utilisation personnelle/interne est conforme au §1.7(a)',
            'description' => 'Ne pas fournir l’application elle-même comme produit logiciel commercial ; conserver les mentions et respecter les droits accordés.',
        ],
        'evaluation_private' => [
            'label' => 'L\'évaluation est privée et hors production',
            'description' => 'Aucune opération client en direct, pilote payant ou lancement de production dans le cadre de l\'exception d\'évaluation.',
        ],
        'bespoke_runtime_scope' => [
            'label' => 'L\'engagement d\'exécution uniquement répond au §1.7(b)',
            'description' => 'Client identifié et périmètre sur mesure écrit ; vous restez l’exploitant responsable ; usage interne, sans revente du logiciel par le client ni livraison du paquet.',
        ],
        'direct_client' => [
            'label' => 'La route directe vers le client répond au §5.6',
            'description' => 'Véritable travail sur mesure ; le client obtient directement la dépendance officielle et vous autorise. Vous ne fournissez pas votre propre copie de package/fork privé ou un produit réutilisable.',
        ],
        'client_free_scope' => [
            'label' => 'Le client se qualifie indépendamment pour une utilisation interne gratuite',
            'description' => 'Le client satisfait au §1.7(a) à part entière. Elle utilise l\'application pour ses opérations, plutôt que de fournir un service logiciel à des tiers.',
        ],
        'hosted_scope' => [
            'label' => 'Les clients commerciaux bénéficient uniquement d\'un accès à l\'exécution',
            'description' => 'Pour chaque utilisation hébergée sélectionnée, le titulaire de licence du produit le possède et l\'exploite, directement ou par l\'intermédiaire de personnel autorisé. Les clients bénéficient uniquement d\'un accès à l\'exécution, sans installation contrôlée par le client, livraison de packages/sources ou droits de revendeur indépendant.',
        ],
    ],
    'ownership' => [
        'one' => [
            'label' => 'Une personne physique ou morale',
            'description' => 'Comprend les départements et les marques de cette même entité ; exclut les sociétés constituées séparément.',
        ],
        'group' => [
            'label' => 'Société mère + filiales en propriété exclusive',
            'description' => '100 % de participation et de droits de vote via la chaîne de qualification de la société mère désignée.',
        ],
        'other' => [
            'label' => 'Autres propriétaires/exploitants',
            'description' => 'Sociétés en propriété partielle, sociétés sœurs, coentreprises ou clients non liés ; évaluer leurs licences séparément.',
        ],
    ],
    'products' => [
        'one' => [
            'label' => 'Un produit',
            'description' => 'Les versions, langues, régions et instances de locataire du même produit sont incluses.',
        ],
        'two' => [
            'label' => 'Deux produits indépendants',
            'description' => 'Identités de produit séparées, plutôt que modules ou domaines de la même application.',
        ],
        'portfolio' => [
            'label' => 'Trois ou plus / futur portefeuille',
            'description' => 'Plusieurs produits proposés indépendamment, détenus et exploités par le titulaire de licence.',
        ],
    ],
    'outcomes' => [
        'mixed' => [
            'title' => 'Différentes utilisations nécessitent une couverture différente',
            'detail' => 'Il n\'existe pas de recommandation unique couvrant chaque activité sélectionnée. Examinez chaque résultat ci-dessous, y compris les conditions nécessitant encore une confirmation.',
        ],
        'free' => [
            'title' => 'Peut être éligible à une utilisation gratuite',
            'detail' => 'Aucun achat commercial n\'est requis lorsque toutes les conditions d\'utilisation gratuite pertinentes sont remplies. Les restrictions et avis de redistribution s’appliquent toujours.',
        ],
        'single' => [
            'title' => 'Single Product · $249',
            'detail' => 'Un produit désigné et un titulaire. Licence Standard accordée une seule fois, avec utilisateurs, locataires, développeurs et environnements illimités dans le périmètre convenu.',
        ],
        'two_single' => [
            'title' => 'Deux licences Single Product · 498 $',
            'detail' => 'Deux licences Single Product coûtent moins cher aux tarifs actuels. Unlimited Company (699 $) couvre aussi le portefeuille actuel et futur de la même entité.',
        ],
        'unlimited' => [
            'title' => 'Unlimited Company · $699',
            'detail' => 'Les produits hébergés actuels et futurs éligibles d’une entité désignée. Dès trois produits, cette offre coûte moins cher que trois licences Single Product ; des licences séparées restent possibles.',
        ],
        'enterprise' => [
            'title' => 'Enterprise Group · $1,499',
            'detail' => 'Une licence commune pour la société mère désignée et ses filiales détenues à 100 %. Des licences séparées adaptées peuvent constituer une alternative ; le périmètre du groupe n’inclut ni OEM ni livraison au client.',
        ],
        'separate' => [
            'title' => 'Licences séparées ou périmètre négocié',
            'detail' => 'Ces entités ne bénéficient pas automatiquement d’une licence partagée. Des licences Single Product ou Unlimited Company adaptées à chaque propriétaire, ou un périmètre expressément convenu, peuvent convenir. Contactez-nous en précisant la structure de propriété.',
        ],
        'custom' => [
            'title' => 'Personnalisé / OEM · devis individuel',
            'detail' => 'Les devis commencent à 2 999 $ pour une portée étroitement spécifiée, sans droits illimités ni frais fixes. Les plans standards n\'autorisent pas cette activité.',
        ],
        'review' => [
            'title' => 'Confirmez la portée avant de continuer',
            'detail' => 'Une condition manquante ou ambiguë empêche une utilisation gratuite ou une recommandation standard. Contactez l\'auteur avec le modèle de propriété et de livraison.',
        ],
        'incomplete' => [
            'title' => 'Un peu plus d\'informations sont nécessaires',
            'detail' => 'Sélectionnez l\'utilisation prévue et les options de propriété/produit pertinentes.',
        ],
    ],
    'reasons' => [
        'mixed_uses' => 'Votre sélection regroupe des activités avec différentes filières de licence ou des conditions non confirmées.',
        'combined_uses' => 'Chaque utilisation sélectionnée a été évaluée séparément ; les résultats ci-dessous partagent la même catégorie de licence.',
        'mixed_owners' => 'Vos propres produits hébergés et les produits hébergés appartenant au client nécessitent une couverture adéquate distincte. Une licence d\'agence ne couvre pas les deux propriétaires.',
        'client_distribution' => 'Le client doit obtenir des droits spécifiques expressément convenus pour distribuer le logiciel à ses propres clients. L’exception pour les prestations sur mesure d’une agence n’accorde pas ces droits.',
        'client_free_scope' => 'Confirmez que le client remplit de manière indépendante toutes les conditions d\'utilisation interne applicables.',
        'choose_use' => 'Choisissez au moins une utilisation prévue.',
        'choose_products' => 'Choisissez le nombre de produits indépendants.',
        'choose_ownership' => 'Identifiez le propriétaire/exploitant.',
        'distribution' => 'Les copies client, les kits réutilisables et les droits OEM/revendeur indépendants nécessitent une portée de distribution expressément convenue.',
        'direct_client' => 'Choisissez l\'utilisation du client et confirmez les véritables conditions client directe sur mesure. La licence d’une agence ne peut se substituer aux droits du client.',
        'hosted_scope' => 'Confirmez la propriété du produit, l\'hébergement contrôlé par le fournisseur et l\'accès client uniquement à l\'exécution. La livraison contrôlée par le client suit un itinéraire différent.',
        'group' => 'Les produits commerciaux sélectionnés couvrent un groupe de sociétés en propriété exclusive éligible.',
        'other_entities' => 'Les propriétaires/exploitants sélectionnés ne font pas partie d\'une seule entité et du groupe en propriété exclusive éligible.',
        'one_product' => 'L\'utilisation sélectionnée est un produit hébergé commercial détenu/exploité par un seul titulaire de licence.',
        'two_products' => 'Vous avez sélectionné deux produits commerciaux hébergés indépendants du même titulaire de licence.',
        'portfolio' => 'Vous avez sélectionné trois produits ou plus, ou une couverture de portefeuille pour les futurs produits.',
        'evaluation' => 'L\'évaluation est gratuite uniquement avant la production, les opérations client en direct et les pilotes payants.',
        'bespoke_runtime' => 'Les conditions documentées d\'engagement, d\'utilisation interne et d\'accès à l\'exécution uniquement doivent toutes être remplies.',
        'free_scope' => 'Confirmez que l\'application est destinée à des opérations personnelles/internes éligibles, et non à un produit logiciel fourni à des tiers.',
        'client_free' => 'Une véritable application interne sur mesure appartenant au client peut bénéficier du droit d\'utilisation gratuite du client.',
        'permitted_free' => 'L\'utilisation que vous avez sélectionnée et les conditions confirmées correspondent aux itinéraires d\'utilisation gratuite résumés.',
    ],
];
