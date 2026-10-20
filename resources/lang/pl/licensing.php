<?php

declare(strict_types=1);

return [
    'title' => 'Przewodnik po licencji',
    'license_label' => 'Licencja',
    'scope_internal' => 'Użytek prywatny i wewnętrzny · Bezpłatnie',
    'scope_hosted' => 'Komercyjny SaaS · Standard',
    'scope_custom' => 'Dystrybucja · Custom',
    'notice_title' => 'Dobierz licencję do swojego projektu.',
    'notice' => 'Użytek prywatny i narzędzia wewnętrzne spełniające warunki licencji są bezpłatne. Komercyjny SaaS wymaga licencji Standard, a instalacje u klientów, gotowe startery i OEM — uzgodnionych uprawnień Custom.',
    'check_scope' => 'Dobierz licencję',
    'intro' => 'Oceń funkcję korzystającą z FLEX Fields, a nie każdą funkcję w tej samej aplikacji Laravel. Wspólny kod ani osobny panel Filament nie rozstrzygają, czy dane użycie jest bezpłatne czy komercyjne.',
    'selection_hint' => 'Zaznacz każde planowane zastosowanie FLEX i potwierdź warunki. Monitoring wewnętrzny może pozostać bezpłatny obok odrębnego produktu komercyjnego; konfiguracja jego funkcji przez pracowników jest komercyjna. Sam tryb odczytu lub edycji nie rozstrzyga. Każde użycie oceniamy osobno; liczymy odrębne produkty, nie użytkowników, domeny czy serwery.',
    'assess' => 'Sprawdź zastosowanie',
    'assessing' => 'Sprawdzamy…',
    'result' => 'Twój zakres zastosowania',
    'contact' => 'Ustal zakres i zakup licencji',
    'plans' => 'Plany i zakup',
    'client_note' => 'Dla aplikacji pisanej na zamówienie i należącej do klienta licencjobiorcą jest klient. Licencja agencji nie obejmuje produktów jej klientów.',
    'terms_note' => 'Przewodnik podsumowuje LICENSE v2.0 i COMMERCIAL.md; nie udziela ani nie aktywuje licencji. Obowiązują pełne warunki, Twoje License Confirmation i uzgodniony zakres Custom. Ceny w USD nie obejmują podatków; zakres jest potwierdzany przed płatnością.',
    'existing_note' => 'Masz już licencję? Wcześniejsze licencje zachowują uzgodnioną wersję i zakres. Ten przewodnik nie rozstrzyga, czy wcześniejsza lub indywidualna licencja obejmuje Twoje zastosowanie.',
    'breakdown' => 'Ocena poszczególnych zastosowań',
    'mixed_note' => 'Każdy wynik dotyczy wskazanego zastosowania. Wymóg Custom w jednym projekcie nie zastępuje ani nie rozszerza uprawnień potrzebnych w innym.',
    'client_use' => [
        'internal' => [
            'label' => 'Wewnętrzne działania operacyjne klienta',
            'description' => 'FLEX obsługuje administrację lub monitoring firmy klienta, bez tworzenia ani konfiguracji jego komercyjnego produktu programistycznego. Wspólny backend nie rozstrzyga zakresu.',
        ],
        'hosted' => [
            'label' => 'Komercyjny produkt hostowany klienta',
            'description' => 'Klient posiada własny SaaS, a użytkownicy otrzymują tylko dostęp runtime. Klient potrzebuje odpowiedniej własnej licencji.',
        ],
        'distribution' => [
            'label' => 'Klient dystrybuuje oprogramowanie lub instalacje',
            'description' => 'Klient dostarcza swoim odbiorcom kopie zawierające pakiet, gotowe startery, instalacje lub niezależne prawa OEM.',
        ],
    ],
    'questions' => [
        'client_use' => 'Jak klient będzie używać swojej aplikacji?',
        'client_ownership' => 'Kto jest właścicielem i operatorem produktów hostowanych klienta?',
        'client_products' => 'Ile produktów hostowanych posiada klient?',
        'uses' => 'Do czego użyjesz FLEX?',
        'conditions' => 'Potwierdź właściwe warunki',
        'ownership' => 'Kto jest właścicielem i operatorem Twoich produktów hostowanych?',
        'products' => 'Ile własnych produktów hostowanych planujesz?',
    ],
    'uses' => [
        'personal' => [
            'label' => 'Prywatnie, edukacyjnie lub badawczo',
            'description' => 'Projekt prywatny, nauka lub badania, a nie prowadzenie komercyjnego produktu programistycznego.',
        ],
        'internal' => [
            'label' => 'Własne działania operacyjne firmy',
            'description' => 'Kwalifikujący się CRM, sklep, rezerwacje, kadry, księgowość lub monitoring operacyjny. Sam wspólny backend z produktem komercyjnym nie czyni tego zastosowania FLEX komercyjnym.',
        ],
        'evaluation' => [
            'label' => 'Prywatny rozwój i testowanie',
            'description' => 'Ocena przyszłego produktu przed obsługą prawdziwych klientów, płatnym pilotażem i produkcją.',
        ],
        'bespoke_runtime' => [
            'label' => 'Wewnętrzna aplikacja obsługiwana dla klienta',
            'description' => 'Udokumentowane zlecenie na miarę; Ty obsługujesz aplikację, a klient otrzymuje tylko dostęp przez interfejs/API.',
        ],
        'bespoke_client' => [
            'label' => 'Aplikacja na zamówienie należąca do klienta',
            'description' => 'Klient pobiera oficjalny pakiet na podstawie własnych uprawnień; działasz jako upoważniony wykonawca.',
        ],
        'hosted' => [
            'label' => 'Komercyjny produkt hostowany / SaaS',
            'description' => 'FLEX tworzy funkcję monetyzowanej usługi, wyświetla jej wyniki dla klientów lub konfiguruje reguły i parametry produktu. Dostęp tylko dla pracowników nie zwalnia takich funkcji z licencji komercyjnej.',
        ],
        'distribution' => [
            'label' => 'Instalacje u klientów lub przekazanie pakietu/kodu',
            'description' => 'Twój produkt na serwerze lub w chmurze klienta, kontenery albo kod zawierający pakiet. Nie dotyczy rzeczywistej aplikacji na zamówienie na licencji klienta opisanej wyżej.',
        ],
        'kits' => [
            'label' => 'Wielokrotnie sprzedawany starter, szablon lub SDK',
            'description' => 'Produkt oparty na FLEX Fields, również gdy kupujący sam pobiera oficjalną zależność.',
        ],
        'oem' => [
            'label' => 'OEM, sublicencje lub niezależna odsprzedaż',
            'description' => 'Inna firma może instalować, samodzielnie obsługiwać, dystrybuować lub sublicencjonować produkt oparty na pakiecie.',
        ],
    ],
    'conditions' => [
        'free_scope' => [
            'label' => 'Użytek prywatny/wewnętrzny spełnia §1.7(a)',
            'description' => 'Oceniana funkcja FLEX służy kwalifikującym się działaniom prywatnym/wewnętrznym; nie tworzy ani nie konfiguruje produktu komercyjnego. Odrębna funkcja komercyjna w tym samym backendzie sama nie wyklucza bezpłatnego użycia. Zachowujesz oznaczenia i dozwolony zakres.',
        ],
        'evaluation_private' => [
            'label' => 'Testowanie jest prywatne i nieprodukcyjne',
            'description' => 'Wyjątek nie obejmuje prawdziwych klientów, płatnego pilotażu ani uruchomienia produkcji.',
        ],
        'bespoke_runtime_scope' => [
            'label' => 'Zlecenie z dostępem runtime spełnia §1.7(b)',
            'description' => 'Określony klient i pisemny zakres na miarę; pozostajesz odpowiedzialnym operatorem; użytek wewnętrzny, bez odsprzedaży oprogramowania i przekazywania pakietu.',
        ],
        'direct_client' => [
            'label' => 'Bezpośrednia licencja klienta spełnia §5.6',
            'description' => 'Rzeczywisty projekt na zamówienie; klient bezpośrednio pobiera oficjalną zależność i upoważnia Cię. Nie przekazujesz własnej kopii/forka pakietu ani gotowego produktu wielokrotnego użytku.',
        ],
        'client_free_scope' => [
            'label' => 'Klient sam spełnia warunki bezpłatnego użytku wewnętrznego',
            'description' => 'Klient sam spełnia §1.7(a). Oceniane zastosowanie FLEX obsługuje jego działania wewnętrzne, bez tworzenia lub konfiguracji produktu komercyjnego; sam wspólny backend nie rozstrzyga.',
        ],
        'hosted_scope' => [
            'label' => 'Komercyjni klienci otrzymują tylko dostęp runtime',
            'description' => 'W każdym wybranym zastosowaniu hostowanym licencjobiorca jest właścicielem i operatorem produktu, bezpośrednio lub przez upoważnione osoby. Klienci otrzymują tylko dostęp runtime: bez własnych instalacji, kopii pakietu/kodu i niezależnych praw odsprzedaży.',
        ],
    ],
    'ownership' => [
        'one' => [
            'label' => 'Jedna osoba lub osoba prawna',
            'description' => 'Obejmuje działy i marki tej samej firmy, a nie odrębne spółki.',
        ],
        'group' => [
            'label' => 'Spółka dominująca i w pełni należące do niej spółki',
            'description' => '100% udziałów i praw głosu w kwalifikującym się łańcuchu własności wskazanej spółki dominującej.',
        ],
        'other' => [
            'label' => 'Inni właściciele/operatorzy',
            'description' => 'Częściowa własność, spółki siostrzane, joint venture lub niezależni klienci; ich uprawnienia wymagają osobnej oceny.',
        ],
    ],
    'products' => [
        'one' => [
            'label' => 'Jeden produkt',
            'description' => 'Obejmuje wersje, języki, regiony i instancje tenantów tego samego produktu.',
        ],
        'two' => [
            'label' => 'Dwa niezależne produkty',
            'description' => 'Odrębne tożsamości produktów, a nie moduły lub domeny tej samej aplikacji.',
        ],
        'portfolio' => [
            'label' => 'Trzy lub więcej / przyszłe portfolio',
            'description' => 'Wiele odrębnie oferowanych produktów należących do licencjobiorcy i przez niego obsługiwanych.',
        ],
    ],
    'outcomes' => [
        'mixed' => [
            'title' => 'Różne zastosowania wymagają różnych uprawnień',
            'detail' => 'Jedna rekomendacja nie obejmuje wszystkich zaznaczonych działań. Sprawdź każdy wynik poniżej, również warunki wymagające potwierdzenia.',
        ],
        'free' => [
            'title' => 'Może kwalifikować się do bezpłatnego użytku',
            'detail' => 'Zakup komercyjny nie jest wymagany, jeśli spełniasz wszystkie właściwe warunki. Nadal obowiązują ograniczenia dystrybucji i zachowanie oznaczeń.',
        ],
        'single' => [
            'title' => 'Single Product · 249 USD',
            'detail' => 'Jeden wskazany produkt i jeden licencjobiorca. Jednorazowa licencja Standard, bez limitów użytkowników, tenantów, programistów i środowisk w jego zakresie.',
        ],
        'two_single' => [
            'title' => 'Dwie licencje Single Product · 498 USD',
            'detail' => 'Przy obecnych cenach dwie licencje Single kosztują mniej. Unlimited Company (699 USD) jest alternatywą obejmującą obecne i przyszłe portfolio tej samej firmy.',
        ],
        'unlimited' => [
            'title' => 'Unlimited Company · 699 USD',
            'detail' => 'Kwalifikujące się obecne i przyszłe produkty hostowane jednej wskazanej firmy. Przy trzech produktach kosztuje mniej niż trzy Single; osobne Single pozostają opcją.',
        ],
        'enterprise' => [
            'title' => 'Enterprise Group · 1499 USD',
            'detail' => 'Wspólna licencja dla wskazanej spółki dominującej i spółek w 100% do niej należących. Odpowiednie oddzielne licencje mogą być alternatywą; zakres nie obejmuje OEM ani dostarczania kopii klientom.',
        ],
        'separate' => [
            'title' => 'Oddzielne licencje lub uzgodniony zakres',
            'detail' => 'Te podmioty nie dzielą automatycznie jednej licencji. Mogą pasować właściwe Single/Unlimited dla każdego właściciela lub uzgodnione pokrycie; opisz autorowi strukturę własności.',
        ],
        'custom' => [
            'title' => 'Custom / OEM · indywidualna wycena',
            'detail' => 'Wyceny zaczynają się od 2999 USD dla ściśle określonego zakresu, nie dla nieograniczonych praw ani jednej stałej ceny. Plany Standard nie zezwalają na to zastosowanie.',
        ],
        'review' => [
            'title' => 'Potwierdź zakres przed rozpoczęciem',
            'detail' => 'Brak lub niejasność warunku nie pozwala wskazać bezpłatnego użytku lub Standard. Opisz autorowi własność produktu i sposób jego dostarczania.',
        ],
        'incomplete' => [
            'title' => 'Potrzebujemy jeszcze kilku informacji',
            'detail' => 'Zaznacz zastosowanie oraz odpowiednią własność i liczbę produktów.',
        ],
    ],
    'reasons' => [
        'mixed_uses' => 'Wybrane działania prowadzą do różnych ścieżek licencyjnych lub zawierają niepotwierdzone warunki.',
        'combined_uses' => 'Każde zastosowanie oceniono osobno; poniższe wyniki należą do tej samej kategorii licencyjnej.',
        'mixed_owners' => 'Twoje produkty hostowane i produkty należące do klienta wymagają odrębnych odpowiednich uprawnień. Jedna licencja agencji nie obejmuje obu właścicieli.',
        'client_distribution' => 'Klient potrzebuje wyraźnie uzgodnionych uprawnień Custom do dystrybucji swoim odbiorcom. Wyjątek dla wykonawcy aplikacji na zamówienie ich nie udziela.',
        'client_free_scope' => 'Potwierdź, że klient sam spełnia wszystkie właściwe warunki użytku wewnętrznego.',
        'choose_use' => 'Wybierz co najmniej jedno zastosowanie.',
        'choose_products' => 'Wskaż liczbę niezależnych produktów.',
        'choose_ownership' => 'Wskaż właściciela/operatora.',
        'distribution' => 'Kopie dla klientów, gotowe startery i niezależne prawa OEM/odsprzedaży wymagają wyraźnie uzgodnionego zakresu dystrybucji.',
        'direct_client' => 'Wybierz zastosowanie klienta i potwierdź warunki rzeczywistego projektu na zamówienie na jego własnej licencji. Licencja agencji nie zastępuje uprawnień klienta.',
        'hosted_scope' => 'Potwierdź własność, hosting pod kontrolą dostawcy i wyłącznie dostęp runtime dla klientów. Dostarczanie instalacji pod kontrolą klienta wymaga innego zakresu.',
        'group' => 'Wybrane produkty komercyjne obejmują kwalifikującą się grupę spółek w pełni należących do wskazanej spółki dominującej.',
        'other_entities' => 'Wybrani właściciele/operatorzy wykraczają poza jeden podmiot i kwalifikującą się grupę w 100% zależną.',
        'one_product' => 'Wybrany użytek to komercyjny produkt hostowany należący do jednego licencjobiorcy i przez niego obsługiwany.',
        'two_products' => 'Wybrano dwa niezależne komercyjne produkty hostowane tego samego licencjobiorcy.',
        'portfolio' => 'Wybrano trzy lub więcej produktów albo zakres portfolio dla przyszłych produktów.',
        'evaluation' => 'Bezpłatna ocena dotyczy tylko okresu przed produkcją, obsługą prawdziwych klientów i płatnym pilotażem.',
        'bespoke_runtime' => 'Muszą być spełnione wszystkie warunki udokumentowanego zlecenia, użytku wewnętrznego i wyłącznie dostępu runtime.',
        'free_scope' => 'Potwierdź, że oceniane zastosowanie FLEX to kwalifikująca się administracja lub monitoring, a nie tworzenie lub konfiguracja produktu komercyjnego. Oceń funkcję, a nie granice aplikacji Laravel lub panelu.',
        'client_free' => 'Rzeczywista wewnętrzna aplikacja klienta na zamówienie może kwalifikować się na podstawie własnych bezpłatnych uprawnień klienta.',
        'permitted_free' => 'Wybrane zastosowania i potwierdzone warunki odpowiadają podsumowanym ścieżkom bezpłatnego użytku.',
    ],
];
