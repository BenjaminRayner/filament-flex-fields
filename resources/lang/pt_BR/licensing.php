<?php

declare(strict_types=1);

return [
    'title' => 'Guia de licença',
    'license_label' => 'Licenciamento',
    'scope_internal' => 'Uso pessoal e interno · Gratuito',
    'scope_hosted' => 'SaaS comercial · Padrão',
    'scope_custom' => 'Distribuição · Personalizada',
    'notice_title' => 'A licença certa para o seu projeto.',
    'notice' => 'O uso pessoal e ferramentas internas qualificadas são gratuitos. O SaaS comercial precisa de uma licença padrão; instalações de clientes, kits reutilizáveis e OEM precisam de direitos personalizados acordados.',
    'check_scope' => 'Encontre sua licença',
    'intro' => 'Escolha como você planeja usar o FLEX Fields. Ajudaremos você a identificar o escopo descrito em LICENSE v2.0.',
    'selection_hint' => 'Selecione cada uso pretendido e confirme suas condições. Avaliamos cada uso separadamente, incluindo seus produtos e os produtos de um cliente. A contagem de produtos segue identidades de produtos independentes, e não usuários, domínios ou servidores.',
    'assess' => 'Verifique meu uso',
    'assessing' => 'Verificando…',
    'result' => 'Guia de licença',
    'contact' => 'Discuta o escopo e a compra',
    'plans' => 'Planos e compras',
    'client_note' => 'Para aplicativos personalizados de propriedade do cliente, a licença pertence ao cliente. A licença de portfólio de uma agência não cobre seus clientes.',
    'terms_note' => 'Este guia resume LICENSE v2.0 e COMMERCIAL.md; ele não emite nem ativa uma licença. Os termos completos, sua confirmação de licença e qualquer escopo personalizado acordado prevalecerão. Os preços em dólares americanos excluem impostos aplicáveis; o escopo é confirmado antes do pagamento.',
    'existing_note' => 'Já possui uma licença? Os direitos existentes mantêm a versão e o escopo acordados. Este guia não determina se uma licença anterior ou personalizada cobre seu uso.',
    'breakdown' => 'Avaliação por uso',
    'mixed_note' => 'Cada resultado se aplica apenas ao uso mostrado ao lado dele. Um requisito personalizado para um projeto não substitui nem expande os direitos necessários para outro.',
    'client_use' => [
        'internal' => [
            'label' => 'Operações internas do cliente',
            'description' => 'Um aplicativo personalizado para as operações do próprio cliente, não um produto de software fornecido a terceiros.',
        ],
        'hosted' => [
            'label' => 'Produto comercial hospedado do cliente',
            'description' => 'O cliente possui seu SaaS e os clientes recebem apenas acesso em tempo de execução. O cliente precisa de sua própria licença adequada.',
        ],
        'distribution' => [
            'label' => 'O cliente distribui software ou instalações do cliente',
            'description' => 'O cliente fornece cópias contendo pacotes, kits reutilizáveis, instalações ou direitos de OEM independentes para seus próprios clientes.',
        ],
    ],
    'questions' => [
        'client_use' => 'Como o cliente utilizará seu aplicativo?',
        'client_ownership' => 'Quem possui e opera os produtos hospedados do cliente?',
        'client_products' => 'Quantos produtos hospedados o cliente possui?',
        'uses' => 'Para que você usará o FLEX?',
        'conditions' => 'Confirme as condições relevantes',
        'ownership' => 'Quem possui e opera seus produtos hospedados?',
        'products' => 'Quantos produtos hospedados você possui?',
    ],
    'uses' => [
        'personal' => [
            'label' => 'Uso pessoal, educacional ou de pesquisa',
            'description' => 'Um projeto privado, aprendizado ou pesquisa, em vez de operar um produto de software comercial.',
        ],
        'internal' => [
            'label' => 'Minhas próprias operações comerciais',
            'description' => 'CRM, loja, reservas ou administração de negócios de sua equipe. Um back office se qualifica apenas quando administra o negócio independentemente de um serviço de software que você vende.',
        ],
        'evaluation' => [
            'label' => 'Desenvolvimento e avaliação privada',
            'description' => 'Testar um produto potencial antes de operações reais com o cliente, pilotos pagos ou produção.',
        ],
        'bespoke_runtime' => [
            'label' => 'Ferramenta interna personalizada operada para um cliente',
            'description' => 'Um compromisso documentado de trabalho contratado; você o opera e o cliente recebe apenas acesso de login/API.',
        ],
        'bespoke_client' => [
            'label' => 'Aplicativo personalizado de propriedade do cliente',
            'description' => 'O cliente obtém o pacote oficial sob seus próprios direitos; você atua como prestador autorizado.',
        ],
        'hosted' => [
            'label' => 'Produto hospedado comercial/SaaS',
            'description' => 'Um serviço de software monetizado ou freemium. Uma tela exclusiva para funcionários que configura esse serviço também pertence a esse escopo.',
        ],
        'distribution' => [
            'label' => 'Instalações do cliente ou entrega de pacote/fonte',
            'description' => 'Seu próprio produto no servidor/nuvem, contêineres ou fonte contendo pacotes de um cliente. Exclui a rota genuína sob medida de cliente direto acima.',
        ],
        'kits' => [
            'label' => 'Inicializador, modelo ou kit de desenvolvedor reutilizável',
            'description' => 'Um produto reutilizável construído em torno do FLEX Fields, mesmo que os próprios compradores instalem a dependência oficial.',
        ],
        'oem' => [
            'label' => 'OEM, sublicenciamento ou revenda independente',
            'description' => 'Outra empresa pode instalar, operar de forma independente, redistribuir ou sublicenciar seu produto baseado em software.',
        ],
    ],
    'conditions' => [
        'free_scope' => [
            'label' => 'O uso pessoal/interno atende §1.7(a)',
            'description' => 'Não forneça o próprio aplicativo como produto de software comercial; preserve os avisos e respeite os direitos concedidos.',
        ],
        'evaluation_private' => [
            'label' => 'A avaliação é privada e não produtiva',
            'description' => 'Nenhuma operação ao vivo do cliente, piloto pago ou lançamento de produção sob a exceção de avaliação.',
        ],
        'bespoke_runtime_scope' => [
            'label' => 'O envolvimento somente em tempo de execução atende a §1.7(b)',
            'description' => 'Cliente identificado e escopo personalizado escrito; você continua sendo o operador responsável; uso interno, sem revenda de software cliente ou entrega de pacotes.',
        ],
        'direct_client' => [
            'label' => 'A rota do cliente direto atende §5.6',
            'description' => 'Trabalho genuíno sob medida; o cliente obtém a dependência oficial diretamente e autoriza você. Você não fornece sua própria cópia do pacote/fork privado ou um produto reutilizável.',
        ],
        'client_free_scope' => [
            'label' => 'O cliente se qualifica de forma independente para uso interno gratuito',
            'description' => 'O cliente atende ao §1.7(a) por direito próprio. Ele usa o aplicativo em suas operações, em vez de fornecer um serviço de software a terceiros.',
        ],
        'hosted_scope' => [
            'label' => 'Clientes comerciais recebem apenas acesso em tempo de execução',
            'description' => 'Para cada uso hospedado selecionado, o licenciado do produto o possui e o opera, diretamente ou por meio de pessoal autorizado. Os clientes obtêm acesso apenas em tempo de execução, sem instalação controlada pelo cliente, entrega de pacote/fonte ou direitos de revendedor independente.',
        ],
    ],
    'ownership' => [
        'one' => [
            'label' => 'Uma pessoa física ou jurídica',
            'description' => 'Inclui departamentos e marcas dessa mesma entidade; exclui empresas constituídas separadamente.',
        ],
        'group' => [
            'label' => 'Controladora + subsidiárias integrais',
            'description' => '100% de participação acionária e direitos de voto por meio da cadeia de qualificação da controladora nomeada.',
        ],
        'other' => [
            'label' => 'Outros proprietários/operadores',
            'description' => 'Empresas de propriedade parcial, empresas irmãs, joint ventures ou clientes não relacionados; avaliar as suas licenças separadamente.',
        ],
    ],
    'products' => [
        'one' => [
            'label' => 'Um produto',
            'description' => 'Estão incluídas versões, idiomas, regiões e instâncias de locatário do mesmo produto.',
        ],
        'two' => [
            'label' => 'Dois produtos independentes',
            'description' => 'Identidades de produtos separadas, em vez de módulos ou domínios do mesmo aplicativo.',
        ],
        'portfolio' => [
            'label' => 'Três ou mais/portfólio futuro',
            'description' => 'Vários produtos oferecidos de forma independente, de propriedade e operados pelo licenciado.',
        ],
    ],
    'outcomes' => [
        'mixed' => [
            'title' => 'Usos diferentes precisam de coberturas diferentes',
            'detail' => 'Não existe uma recomendação única que cubra todas as atividades selecionadas. Revise cada resultado abaixo, incluindo as condições que ainda exigem confirmação.',
        ],
        'free' => [
            'title' => 'Pode se qualificar para uso gratuito',
            'detail' => 'Nenhuma compra comercial é necessária quando todas as condições de uso gratuito relevantes forem válidas. Restrições e avisos de redistribuição ainda se aplicam.',
        ],
        'single' => [
            'title' => 'Single Product · $249',
            'detail' => 'Um produto designado e um licenciado. Licença Standard concedida uma única vez, com usuários, locatários, desenvolvedores e ambientes ilimitados dentro do escopo acordado.',
        ],
        'two_single' => [
            'title' => 'Duas licenças Single Product · US$ 498',
            'detail' => 'Duas licenças Single Product custam menos nos preços atuais. Unlimited Company (US$ 699) também cobre o portfólio atual e futuro da mesma entidade.',
        ],
        'unlimited' => [
            'title' => 'Unlimited Company · $699',
            'detail' => 'Produtos hospedados atuais e futuros elegíveis de uma entidade designada. A partir de três produtos, custa menos que três licenças Single Product; licenças separadas continuam sendo uma opção.',
        ],
        'enterprise' => [
            'title' => 'Enterprise Group · $1,499',
            'detail' => 'Uma licença comum para a controladora designada e subsidiárias integrais. Licenças separadas adequadas podem ser uma alternativa; o escopo do grupo não inclui OEM nem entrega ao cliente.',
        ],
        'separate' => [
            'title' => 'Licenças separadas ou cobertura negociada',
            'detail' => 'Essas entidades não compartilham automaticamente uma licença. Licenças Single Product ou Unlimited Company adequadas por proprietário, ou uma cobertura expressamente acordada, podem atender. Entre em contato informando a estrutura de propriedade.',
        ],
        'custom' => [
            'title' => 'Personalizado / OEM · cotado individualmente',
            'detail' => 'As cotações começam em US$ 2.999 para um escopo estritamente especificado, sem direitos ilimitados ou uma taxa fixa. Os planos padrão não autorizam esta atividade.',
        ],
        'review' => [
            'title' => 'Confirme o escopo antes de continuar',
            'detail' => 'Uma condição ausente ou ambígua impede uma recomendação padrão ou de uso livre. Entre em contato com o autor com a propriedade e o modelo de entrega.',
        ],
        'incomplete' => [
            'title' => 'É necessário um pouco mais de informação',
            'detail' => 'Selecione o uso pretendido e as opções de propriedade/produto relevantes.',
        ],
    ],
    'reasons' => [
        'mixed_uses' => 'Sua seleção combina atividades com diferentes rotas de licenciamento ou condições não confirmadas.',
        'combined_uses' => 'Cada uso selecionado foi avaliado separadamente; os resultados abaixo compartilham a mesma categoria de licenciamento.',
        'mixed_owners' => 'Seus próprios produtos hospedados e os produtos hospedados pertencentes ao cliente precisam de cobertura adequada separada. Uma licença da agência não cobre ambos os proprietários.',
        'client_distribution' => 'O cliente precisa de direitos específicos expressamente acordados para distribuir o software aos próprios clientes. A exceção para trabalho sob medida de uma agência não concede esses direitos.',
        'client_free_scope' => 'Confirme se o cliente atende de forma independente a todas as condições de uso interno aplicáveis.',
        'choose_use' => 'Escolha pelo menos um uso pretendido.',
        'choose_products' => 'Escolha a quantidade de produtos independentes.',
        'choose_ownership' => 'Identifique o proprietário/operador.',
        'distribution' => 'Cópias de clientes, kits reutilizáveis e direitos independentes de OEM/revendedor precisam de um escopo de distribuição expressamente acordado.',
        'direct_client' => 'Escolha o uso do cliente e confirme as condições genuínas de cliente direto sob medida. A licença de uma agência não pode substituir os direitos do cliente.',
        'hosted_scope' => 'Confirme a propriedade do produto, hospedagem controlada pelo fornecedor e acesso do cliente somente em tempo de execução. A entrega controlada pelo cliente segue um caminho diferente.',
        'group' => 'Os produtos comerciais selecionados abrangem um grupo corporativo de propriedade integral qualificado.',
        'other_entities' => 'Os proprietários/operadores selecionados estão fora de uma entidade e do grupo de propriedade integral qualificado.',
        'one_product' => 'O uso selecionado é um produto comercial hospedado de propriedade/operado por um licenciado.',
        'two_products' => 'Você selecionou dois produtos comerciais hospedados independentes do mesmo licenciado.',
        'portfolio' => 'Você selecionou três ou mais produtos ou cobertura de portfólio para produtos futuros.',
        'evaluation' => 'A avaliação é gratuita somente antes da produção, operações ao vivo com clientes e pilotos pagos.',
        'bespoke_runtime' => 'O envolvimento documentado, o uso interno e as condições de acesso somente em tempo de execução devem ser mantidos.',
        'free_scope' => 'Confirme se o aplicativo se destina a operações pessoais/internas qualificadas e não a um produto de software fornecido a terceiros.',
        'client_free' => 'Um aplicativo interno genuíno e personalizado de propriedade do cliente pode se qualificar sob o direito de uso gratuito do próprio cliente.',
        'permitted_free' => 'O uso selecionado e as condições confirmadas se enquadram nas rotas de uso livre resumidas.',
    ],
];
