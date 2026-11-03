<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta
        name="description"
        content="SMART MASIPIT: barangay information system for resident registration, document processing, and authorized records management."
    >

    <title>SMART MASIPIT | Barangay Masipit</title>

    <style>
        :root {
            --green: #155c45;
            --green-dark: #103d30;
            --green-light: #eaf3ed;
            --gold: #e2bd70;
            --cream: #f8f7f2;
            --text: #21392f;
            --muted: #5e7067;
            --line: #dce5dd;
            --white: #ffffff;
        }

        * { box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            margin: 0;
            background: var(--cream);
            color: var(--text);
            font-family: "Segoe UI", Arial, sans-serif;
            line-height: 1.65;
        }

        a { color: inherit; }

        button, input { font: inherit; }

        :focus-visible {
            outline: 3px solid #a5751a;
            outline-offset: 5px;
        }

        .container {
            width: min(1140px, calc(100% - 48px));
            margin-inline: auto;
        }

        .skip-link {
            position: absolute;
            left: 20px;
            top: -100px;
            padding: 12px 18px;
            background: white;
            z-index: 20;
        }

        .skip-link:focus { top: 12px; }

        .location-bar {
            padding: 9px 0;
            background: var(--green-dark);
            color: #e4eee7;
            font-size: 12px;
            letter-spacing: .6px;
        }

        .site-header {
            background: var(--cream);
            border-bottom: 1px solid var(--line);
        }

        .header-inner {
            min-height: 94px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .brand-mark {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 12px;
            background: var(--green);
            color: white;
            font-size: 19px;
            font-weight: 800;
        }

        .brand-name {
            display: block;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: .7px;
        }

        .brand-subtitle {
            display: block;
            color: var(--muted);
            font-size: 11px;
            letter-spacing: .4px;
        }

        .main-nav {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 25px;
        }

        .main-nav > a {
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .main-nav > a:hover { color: var(--green); }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 47px;
            padding: 12px 21px;
            border: 1px solid var(--green);
            border-radius: 8px;
            background: var(--green);
            color: white;
            font-weight: 650;
            font-size: 14px;
            text-decoration: none;
            text-align: center;
            transition: background .15s ease;
        }

        .button:hover {
            background: var(--green-dark);
            color: white;
        }

        .button.outline {
            background: transparent;
            color: var(--green);
            border-color: #bacdc0;
        }

        .button.outline:hover { background: var(--green-light); }

        .button.gold {
            background: var(--gold);
            border-color: var(--gold);
            color: var(--green-dark);
        }

        .button.gold:hover { background: #edcb88; }

        .hero {
            padding: 78px 0 72px;
            overflow: hidden;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1.12fr 1fr;
            align-items: center;
            gap: 70px;
        }

        .eyebrow {
            margin: 0 0 18px;
            color: var(--green);
            font-size: 12px;
            font-weight: 750;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-size: clamp(36px, 4.8vw, 60px);
            line-height: 1.12;
            letter-spacing: -2px;
            font-weight: 800;
        }

        h1 span { color: var(--green); }

        .hero-copy {
            max-width: 540px;
            margin: 24px 0;
            color: var(--muted);
            font-size: 17px;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .hero-note {
            margin-top: 18px;
            color: var(--muted);
            font-size: 12px;
        }

        .welcome-panel {
            position: relative;
            padding: 32px;
            background: var(--green-dark);
            color: white;
            border-radius: 20px;
            box-shadow: 16px 16px 0 #e4eadd;
        }

        .panel-label {
            margin: 0 0 24px;
            color: #cbdcd1;
            font-size: 11px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .welcome-panel h2 {
            max-width: 330px;
            margin: 0 0 25px;
            color: white;
            font-size: 29px;
            line-height: 1.3;
        }

        .panel-row {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 20px 0;
            border-top: 1px solid #406354;
        }

        .step-number {
            width: 32px;
            height: 32px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border: 1px solid #819b88;
            border-radius: 50%;
            color: var(--gold);
            font-size: 12px;
            font-weight: 700;
        }

        .panel-row strong { display: block; font-size: 15px; }

        .panel-row p {
            margin: 5px 0 0;
            color: #d0dfd5;
            font-size: 13px;
        }

        .principles {
            border-block: 1px solid var(--line);
            background: white;
        }

        .principles-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
        }

        .principle { padding: 25px 24px; }

        .principle + .principle { border-left: 1px solid var(--line); }

        .principle strong { display: block; font-size: 15px; }

        .principle span { color: var(--muted); font-size: 13px; }

        .section { padding: 76px 0; }

        .section-heading { max-width: 660px; margin-bottom: 32px; }

        .section-heading h2 {
            margin: 0 0 14px;
            font-size: clamp(28px, 3vw, 38px);
            line-height: 1.2;
            letter-spacing: -1px;
        }

        .section-heading p { margin: 0; color: var(--muted); }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .service-card {
            padding: 28px;
            background: white;
            border: 1px solid var(--line);
            border-radius: 12px;
        }

        .card-number {
            margin-bottom: 30px;
            color: var(--green);
            font-size: 14px;
            font-weight: 750;
        }

        .service-card h3 { margin: 0 0 12px; font-size: 21px; }

        .service-card p {
            margin: 0 0 22px;
            color: var(--muted);
            font-size: 14px;
        }

        .tag {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            background: var(--green-light);
            color: var(--green-dark);
            font-size: 11px;
            font-weight: 650;
        }

        .about-section { background: #eaf0e9; }

        .about-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: start;
        }

        .about-grid p { color: var(--muted); }

        .about-note {
            padding-left: 24px;
            border-left: 3px solid var(--green);
        }

        .about-note h3 { margin-top: 0; font-size: 22px; }

        .faq-layout {
            display: grid;
            grid-template-columns: .8fr 1.2fr;
            gap: 64px;
        }

        details { border-bottom: 1px solid var(--line); }

        summary {
            padding: 22px 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 650;
        }

        details p {
            margin: 0;
            padding: 0 4px 22px;
            color: var(--muted);
            font-size: 14px;
        }

        .cta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 28px;
            padding: 36px;
            background: var(--green-dark);
            color: white;
            border-radius: 14px;
        }

        .cta h2 { margin: 0 0 8px; font-size: 26px; }
        .cta p { margin: 0; color: #cbdcd1; }

        footer {
            margin-top: 64px;
            padding: 28px 0;
            border-top: 1px solid var(--line);
        }

        .footer-inner {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 16px;
            color: var(--muted);
            font-size: 12px;
        }

        .footer-inner a { margin-left: 18px; }

        @media (max-width: 960px) {
            .header-inner { flex-wrap: wrap; padding-block: 20px; }
            .hero-grid { gap: 35px; }
            .cards { grid-template-columns: 1fr; }
            .card-number { margin-bottom: 18px; }
            .faq-layout { gap: 35px; }
        }

        @media (max-width: 700px) {
            .container { width: min(100% - 32px, 1140px); }
            .main-nav { gap: 16px; }
            .hero { padding: 48px 0; }
            .hero-grid,
            .about-grid,
            .faq-layout { grid-template-columns: 1fr; }
            .welcome-panel { margin: 12px 12px 10px 0; }
            .principles-grid { grid-template-columns: 1fr; }
            .principle { padding: 18px 0; }
            .principle + .principle {
                border-left: 0;
                border-top: 1px solid var(--line);
            }
            .section { padding: 50px 0; }
            .cta { flex-direction: column; align-items: flex-start; }
            .footer-inner a { margin-left: 0; margin-right: 18px; }
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .button { transition: none; }
        }
    </style>
</head>

<body>
@php
    $registrationAvailable = Route::has('register');

    $accountUrl = null;
    $accountLabel = null;

    if (auth()->check()) {
        $approved = auth()->user()->canAccessServices();

        $accountUrl = route(
            $approved ? 'dashboard' : 'account.status'
        );

        $accountLabel = $approved
            ? 'Open dashboard'
            : 'View account status';
    }
@endphp

<a class="skip-link" href="#main-content">Skip to main content</a>

<div class="location-bar">
    <div class="container">
        BARANGAY MASIPIT · CALAPAN CITY · ORIENTAL MINDORO
    </div>
</div>

<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="{{ route('home') }}"
           aria-label="SMART MASIPIT home">
            <span class="brand-mark" aria-hidden="true">SM</span>

            <span>
                <span class="brand-name">SMART MASIPIT</span>
                <span class="brand-subtitle">
                    Barangay Information System
                </span>
            </span>
        </a>

        <nav class="main-nav" aria-label="Main navigation">
            <a href="#services">Mga serbisyo</a>
            <a href="#about">Tungkol sa sistema</a>
            <a href="#faq">Mga tanong</a>

            @auth
                <a class="button" href="{{ $accountUrl }}">
                    {{ $accountLabel }}
                </a>
            @else
                <a class="button" href="{{ route('login') }}">
                    Sign in
                </a>
            @endauth
        </nav>
    </div>
</header>

<main id="main-content">
    <section class="hero" aria-labelledby="hero-heading">
        <div class="container hero-grid">
            <div>
                <p class="eyebrow">Para sa komunidad ng Masipit</p>

                <h1 id="hero-heading">
                    Serbisyong barangay,<br>
                    <span>mas madaling lapitan.</span>
                </h1>

                <p class="hero-copy">
                    Kilalanin ang SMART MASIPIT—isang sistema para sa
                    mas organisadong resident records, pagproseso ng
                    dokumento, at pamamahala ng health records.
                </p>

                <div class="hero-actions">
                    @auth
                        <a class="button" href="{{ $accountUrl }}">
                            {{ $accountLabel }} →
                        </a>
                    @else
                        @if ($registrationAvailable)
                            <a class="button" href="{{ route('register') }}">
                                Create resident account →
                            </a>
                        @else
                            <a class="button" href="{{ route('login') }}">
                                Sign in →
                            </a>
                        @endif
                    @endauth

                    <a class="button outline" href="#services">
                        Alamin ang mga serbisyo
                    </a>
                </div>

                <p class="hero-note">
                    Resident accounts require verification by the
                    Barangay Secretary or Barangay Captain.
                </p>
            </div>

            <aside class="welcome-panel"
                   aria-labelledby="process-heading">
                <p class="panel-label">Resident account process</p>

                <h2 id="process-heading">
                    Magsimula sa tamang impormasyon.
                </h2>

                <div class="panel-row">
                    <span class="step-number" aria-hidden="true">01</span>
                    <div>
                        <strong>Mag-register</strong>
                        <p>
                            Ilagay ang iyong personal details at
                            tirahan sa Barangay Masipit.
                        </p>
                    </div>
                </div>

                <div class="panel-row">
                    <span class="step-number" aria-hidden="true">02</span>
                    <div>
                        <strong>Magpa-verify</strong>
                        <p>
                            Kailangang suriin ng Secretary o Captain
                            ang iyong residency bago ma-activate
                            ang resident services.
                        </p>
                    </div>
                </div>

                <div class="panel-row">
                    <span class="step-number" aria-hidden="true">03</span>
                    <div>
                        <strong>Gamitin ang available na serbisyo</strong>
                        <p>
                            Kapag approved, makikita sa iyong account
                            ang mga serbisyong maaari mong gamitin.
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <section class="principles" aria-label="Account access principles">
        <div class="container principles-grid">
            <div class="principle">
                <strong>Verified resident accounts</strong>
                <span>May barangay review bago ang activation.</span>
            </div>

            <div class="principle">
                <strong>Access ayon sa tungkulin</strong>
                <span>Magkahiwalay ang resident at staff permissions.</span>
            </div>

            <div class="principle">
                <strong>Personal na impormasyon</strong>
                <span>Hindi ipinapakita ang resident records sa publiko.</span>
            </div>
        </div>
    </section>

    <section id="services" class="section"
             aria-labelledby="services-heading">
        <div class="container">
            <div class="section-heading">
                <p class="eyebrow">Sakop ng SMART MASIPIT</p>

                <h2 id="services-heading">
                    Magkakaugnay na serbisyo.<br>
                    Mas organisadong impormasyon.
                </h2>

                <p>
                    Unti-unting binubuo ang mga module ng sistema.
                    Makikita rito ang kanilang layunin at kasalukuyang
                    availability.
                </p>
            </div>

            <div class="cards">
                <article class="service-card">
                    <div class="card-number">01 / RESIDENT RECORDS</div>

                    <h3>Resident Registry</h3>

                    <p>
                        Pagrehistro, paghahanap, at pag-update ng resident
                        records para sa awtorisadong barangay personnel.
                        Kasama sa susunod na development ang household records.
                    </p>

                    <span class="tag">Registry: para sa authorized staff</span>
                </article>

                <article class="service-card">
                    <div class="card-number">02 / BARANGAY DOCUMENTS</div>

                    <h3>Document Requests</h3>

                    <p>
                        Pag-request at pagsubaybay sa Barangay Clearance
                        at Certificate of Indigency, na sasailalim sa
                        review at approval ng barangay.
                    </p>

                    <span class="tag">Inihahanda ang module</span>
                </article>

                <article class="service-card">
                    <div class="card-number">03 / HEALTH CENTER</div>

                    <h3>Health Records</h3>

                    <p>
                        Pamamahala ng consultation history at immunization
                        records para lamang sa awtorisadong health personnel.
                    </p>

                    <span class="tag">Inihahanda ang module</span>
                </article>
            </div>
        </div>
    </section>

    <section id="about" class="section about-section"
             aria-labelledby="about-heading">
        <div class="container about-grid">
            <div class="section-heading">
                <p class="eyebrow">Tungkol sa sistema</p>

                <h2 id="about-heading">
                    Dinisenyo para sa pangangailangan ng barangay.
                </h2>

                <p>
                    Ang SMART MASIPIT ay isang integrated web-based
                    information system para sa Barangay Masipit,
                    Calapan City, Oriental Mindoro.
                </p>
            </div>

            <div class="about-note">
                <h3>Isang resident record, magkakaugnay na proseso.</h3>

                <p>
                    Layunin nitong pag-ugnayin ang resident registry,
                    document processing, at health record management
                    gamit ang isang common resident identifier.
                </p>

                <p>
                    Magkakaroon lamang ng access ang bawat account sa
                    impormasyong naaayon sa tungkulin nito. Hindi nagbibigay
                    ng access sa clinical records ang ordinaryong
                    resident account.
                </p>
            </div>
        </div>
    </section>

    <section id="faq" class="section" aria-labelledby="faq-heading">
        <div class="container faq-layout">
            <div class="section-heading">
                <p class="eyebrow">Bago ka magsimula</p>
                <h2 id="faq-heading">Mga madalas itanong</h2>
                <p>
                    Alamin kung paano magsimula at bakit kailangan
                    ang account verification.
                </p>
            </div>

            <div>
                <details>
                    <summary>Sino ang maaaring gumawa ng resident account?</summary>
                    <p>
                        Ang resident registration ay para sa mga
                        naninirahan sa Barangay Masipit. Susuriin muna
                        ang application bago ma-activate ang resident services.
                    </p>
                </details>

                <details>
                    <summary>Automatic bang approved kapag nag-register?</summary>
                    <p>
                        Hindi. Pending muna ang account. Kailangang
                        ma-verify ng Barangay Secretary o Barangay Captain
                        ang residency ng applicant.
                    </p>
                </details>

                <details>
                    <summary>May record na ako sa barangay. Kailangan ko pa bang mag-register?</summary>
                    <p>
                        Magkaiba ang resident record at online account.
                        Kung wala ka pang online account, kailangan mong
                        mag-register. Sa verification, ili-link ito sa
                        tamang existing resident record.
                    </p>
                </details>

                <details>
                    <summary>Puwede ko bang makita ang records ng ibang residente?</summary>
                    <p>
                        Hindi. Ang resident registry ay para sa authorized
                        barangay personnel. Ang resident account ay para
                        sa sarili mong available services.
                    </p>
                </details>

                <details>
                    <summary>Available na ba ang lahat ng modules?</summary>
                    <p>
                        Hindi pa. Kasalukuyang binubuo ang sistema.
                        Ang document requests at health record module
                        ay hindi pa available sa bersyong ito.
                    </p>
                </details>

                <details>
                    <summary>Saan ako lalapit kung may maling impormasyon?</summary>
                    <p>
                        Makipag-ugnayan sa barangay office para ma-review
                        ang iyong impormasyon. Huwag gumawa ng maraming
                        accounts para sa iisang tao.
                    </p>
                </details>
            </div>
        </div>
    </section>

    <div class="container">
        <section class="cta" aria-labelledby="cta-heading">
            <div>
                <h2 id="cta-heading">Magsimula sa iyong resident account.</h2>
                <p>
                    Magbigay ng tamang impormasyon para sa verification.
                </p>
            </div>

            @auth
                <a class="button gold" href="{{ $accountUrl }}">
                    {{ $accountLabel }} →
                </a>
            @else
                @if ($registrationAvailable)
                    <a class="button gold" href="{{ route('register') }}">
                        Create an account →
                    </a>
                @else
                    <a class="button gold" href="{{ route('login') }}">
                        Sign in →
                    </a>
                @endif
            @endauth
        </section>
    </div>
</main>

<footer>
    <div class="container footer-inner">
        <div>
            <strong>SMART MASIPIT</strong><br>
            Barangay Masipit, Calapan City, Oriental Mindoro
        </div>

        <div>
            <a href="#about">Tungkol sa sistema</a>
            <a href="#faq">Mga tanong</a>
        </div>
    </div>
</footer>
</body>
</html>