<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function sendJsonResponse(
    bool $success,
    string $message,
    int $statusCode = 200
): never {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'save_contact_message'
) {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    $website = trim((string) ($_POST['website'] ?? ''));

    /*
     * Honeypot spam protection.
     * Real users never see or complete the website field.
     */
    if ($website !== '') {
        sendJsonResponse(true, 'Message sent successfully.');
    }

    if ($name === '' || $email === '' || $subject === '' || $message === '') {
        sendJsonResponse(
            false,
            'Please complete all contact fields.',
            422
        );
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(
            false,
            'Please enter a valid email address.',
            422
        );
    }

    if (mb_strlen($name) > 120
        || mb_strlen($email) > 190
        || mb_strlen($subject) > 190
        || mb_strlen($message) > 5000
    ) {
        sendJsonResponse(
            false,
            'One or more fields are too long.',
            422
        );
    }

    try {
        $pdo = getDatabaseConnection();

        $statement = $pdo->prepare(
            'INSERT INTO contact_messages
                (full_name, email, subject, message)
             VALUES
                (:full_name, :email, :subject, :message)'
        );

        $statement->execute([
            'full_name' => $name,
            'email'     => $email,
            'subject'   => $subject,
            'message'   => $message,
        ]);

        sendJsonResponse(
            true,
            'Your message was saved successfully.'
        );
    } catch (Throwable $exception) {
        error_log($exception->getMessage());

        sendJsonResponse(
            false,
            'Database connection failed. Please open setup.php first.',
            500
        );
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Portfolio of Cher Micole P. Lirio, BS Information Technology student.">
    <title>Cher Micole P. Lirio | Portfolio</title>

    <link rel="stylesheet" href="style.css">
    <script src="script.js" defer></script>

    <style>
        /* =====================================================
           RESUME PREVIEW MODAL AND ONE-PAGE PDF PRINTING
           Added without removing the user's existing design.
           ===================================================== */

        .resume-pdf-modal {
            position: fixed;
            z-index: 99999;
            inset: 0;
            display: grid;
            padding: 20px;
            place-items: center;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }

        .resume-pdf-modal.open {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .resume-pdf-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(2, 8, 15, 0.86);
            backdrop-filter: blur(12px);
        }

        .resume-pdf-dialog {
            position: relative;
            z-index: 1;
            display: grid;
            width: min(1180px, 100%);
            max-height: calc(100vh - 40px);
            overflow: hidden;
            border: 1px solid var(--border, rgba(165, 193, 224, 0.15));
            border-radius: 24px;
            background: var(--background-two, #0d192a);
            box-shadow: 0 30px 90px rgba(0, 0, 0, 0.42);
            grid-template-rows: auto 1fr;
        }

        .resume-pdf-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 20px 22px;
            border-bottom: 1px solid var(--border, rgba(165, 193, 224, 0.15));
            background: var(--card, #111f33);
        }

        .resume-pdf-toolbar h2 {
            margin: 4px 0 0;
            font-size: 1.35rem;
            letter-spacing: -0.03em;
        }

        .resume-pdf-toolbar-description {
            display: block;
            margin-top: 5px;
            color: var(--muted, #92a1b5);
            font-size: 0.78rem;
        }

        .resume-pdf-actions {
            display: flex;
            flex: 0 0 auto;
            gap: 10px;
        }

        .resume-pdf-actions .button {
            width: auto;
        }

        .resume-pdf-scroll {
            overflow: auto;
            padding: 30px;
            background:
                linear-gradient(rgba(20, 32, 48, 0.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(20, 32, 48, 0.06) 1px, transparent 1px),
                #cfd4db;
            background-size: 22px 22px;
        }

        .resume-pdf-paper {
            width: min(100%, 210mm);
            min-height: 297mm;
            margin: 0 auto;
            background: #ffffff;
            box-shadow: 0 25px 75px rgba(0, 0, 0, 0.27);
        }

        /* Styled A4 resume inside the modal */
        .resume-pdf-paper .resume-template {
            width: 100%;
            min-height: 297mm;
            overflow: hidden;
            border: 0;
            border-radius: 0;
            background: #ffffff;
            color: #172033;
            box-shadow: none;
        }

        .resume-pdf-paper .resume-template,
        .resume-pdf-paper .resume-template * {
            box-sizing: border-box;
        }

        .resume-pdf-paper .resume-top {
            background:
                linear-gradient(110deg, rgba(19, 132, 95, 0.11), transparent 62%),
                #ffffff;
        }

        .resume-pdf-paper .resume-top h3,
        .resume-pdf-paper .resume-item h5 {
            color: #172033;
        }

        .resume-pdf-paper .resume-top p,
        .resume-pdf-paper .resume-block p,
        .resume-pdf-paper .resume-block li {
            color: #526176;
        }

        .resume-pdf-paper .resume-top span,
        .resume-pdf-paper .resume-block h4 {
            color: #117755;
        }

        .resume-pdf-paper .resume-label {
            color: #356ed2 !important;
        }

        .resume-pdf-paper .resume-columns > aside {
            background: #eef4f1;
        }

        .resume-pdf-paper .resume-photo {
            border-color: #15966c;
        }

        @media (max-width: 760px) {
            .resume-pdf-modal {
                padding: 10px;
            }

            .resume-pdf-dialog {
                max-height: calc(100vh - 20px);
                border-radius: 17px;
            }

            .resume-pdf-toolbar {
                align-items: stretch;
                flex-direction: column;
                padding: 17px;
            }

            .resume-pdf-actions {
                display: grid;
                grid-template-columns: 1fr;
            }

            .resume-pdf-actions .button {
                width: 100%;
            }

            .resume-pdf-scroll {
                padding: 12px;
            }

            .resume-pdf-paper {
                width: 210mm;
                transform: scale(0.55);
                transform-origin: top left;
                margin-bottom: calc(-297mm * 0.45);
            }
        }

        /* Chrome/Edge PDF page setup */
        @page {
            size: A4 portrait;
            margin: 0;
        }

        @media print {
            html,
            body {
                width: 210mm !important;
                height: 297mm !important;
                min-height: 297mm !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
                background: #ffffff !important;
            }

            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /*
             * display:none removes the website from the print layout.
             * This prevents the previous 9 blank pages.
             */
            body > *:not(#resumePdfModal) {
                display: none !important;
            }

            #resumePdfModal,
            #resumePdfModal * {
                visibility: visible !important;
            }

            #resumePdfModal {
                position: static !important;
                display: block !important;
                width: 210mm !important;
                height: 297mm !important;
                margin: 0 !important;
                padding: 0 !important;
                opacity: 1 !important;
                visibility: visible !important;
                pointer-events: auto !important;
                background: #ffffff !important;
            }

            .resume-pdf-backdrop,
            .resume-pdf-toolbar {
                display: none !important;
            }

            .resume-pdf-dialog,
            .resume-pdf-scroll,
            .resume-pdf-paper {
                position: static !important;
                display: block !important;
                width: 210mm !important;
                min-width: 210mm !important;
                max-width: 210mm !important;
                height: 297mm !important;
                min-height: 297mm !important;
                max-height: 297mm !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
                border: 0 !important;
                border-radius: 0 !important;
                background: #ffffff !important;
                box-shadow: none !important;
                transform: none !important;
            }

            #resumePdfPaper .resume-template {
                display: block !important;
                width: 210mm !important;
                height: 297mm !important;
                min-height: 297mm !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
                border: 0 !important;
                border-radius: 0 !important;
                background: #ffffff !important;
                color: #172033 !important;
                box-shadow: none !important;
                page-break-after: avoid !important;
                page-break-before: avoid !important;
                page-break-inside: avoid !important;
                break-after: avoid-page !important;
                break-before: avoid-page !important;
                break-inside: avoid-page !important;
            }

            #resumePdfPaper .resume-top {
                min-height: 54mm !important;
                gap: 7mm !important;
                padding: 6mm 8mm !important;
                border-bottom: 1px solid #d9e0e7 !important;
                background:
                    linear-gradient(110deg, rgba(19, 132, 95, 0.11), transparent 62%),
                    #ffffff !important;
                page-break-inside: avoid !important;
                break-inside: avoid-page !important;
            }

            #resumePdfPaper .resume-photo {
                width: 2in !important;
                height: 2in !important;
                min-width: 2in !important;
                min-height: 2in !important;
                max-width: 2in !important;
                max-height: 2in !important;
                overflow: hidden !important;
                border: 2px solid #15966c !important;
                border-radius: 4mm !important;
                box-shadow: none !important;
            }

            #resumePdfPaper .resume-photo img {
                display: block !important;
                width: 100% !important;
                height: 100% !important;
                object-fit: cover !important;
                object-position: center !important;
            }

            #resumePdfPaper .resume-top h3 {
                margin: 0 !important;
                color: #172033 !important;
                font-size: 17pt !important;
                line-height: 1.05 !important;
            }

            #resumePdfPaper .resume-top p {
                margin-top: 1.5mm !important;
                color: #526176 !important;
                font-size: 9pt !important;
                line-height: 1.25 !important;
            }

            #resumePdfPaper .resume-top span {
                margin-top: 1.5mm !important;
                color: #117755 !important;
                font-size: 7.5pt !important;
            }

            #resumePdfPaper .resume-columns {
                display: grid !important;
                height: 243mm !important;
                grid-template-columns: 34% 66% !important;
                page-break-inside: avoid !important;
                break-inside: avoid-page !important;
            }

            #resumePdfPaper .resume-columns > aside,
            #resumePdfPaper .resume-main {
                height: 243mm !important;
                padding: 5.5mm !important;
                overflow: hidden !important;
            }

            #resumePdfPaper .resume-columns > aside {
                border-right: 1px solid #d9e0e7 !important;
                background: #eef4f1 !important;
            }

            #resumePdfPaper .resume-block {
                margin: 0 !important;
                page-break-inside: avoid !important;
                break-inside: avoid-page !important;
            }

            #resumePdfPaper .resume-block + .resume-block {
                margin-top: 4.2mm !important;
            }

            #resumePdfPaper .resume-block h4 {
                margin: 0 0 2mm !important;
                color: #117755 !important;
                font-size: 7.4pt !important;
                line-height: 1.1 !important;
                letter-spacing: 0.10em !important;
            }

            #resumePdfPaper .resume-block p,
            #resumePdfPaper .resume-block li {
                color: #526176 !important;
                font-size: 7.4pt !important;
                line-height: 1.28 !important;
            }

            #resumePdfPaper .resume-block ul {
                margin: 0 !important;
                padding-left: 4mm !important;
            }

            #resumePdfPaper .resume-block li + li {
                margin-top: 0.7mm !important;
            }

            #resumePdfPaper .resume-item {
                margin: 0 !important;
                padding-left: 3.5mm !important;
                border-left: 1.5px solid #cfd7df !important;
                page-break-inside: avoid !important;
                break-inside: avoid-page !important;
            }

            #resumePdfPaper .resume-item + .resume-item {
                margin-top: 2.7mm !important;
            }

            #resumePdfPaper .resume-label {
                margin: 0 !important;
                color: #356ed2 !important;
                font-size: 6.6pt !important;
                font-weight: 900 !important;
                line-height: 1.15 !important;
            }

            #resumePdfPaper .resume-item h5 {
                margin: 0.7mm 0 0 !important;
                color: #172033 !important;
                font-size: 8.3pt !important;
                line-height: 1.2 !important;
            }

            #resumePdfPaper .resume-item h5 + p {
                margin-top: 0.7mm !important;
            }
        }
    </style>

</head>
<body>
    <header class="header" id="header">
        <nav class="nav container">
            <a href="#home" class="logo">PORTFOLIO</a>

            <button class="menu-button" id="menuButton" type="button" aria-label="Open menu">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <div class="nav-links" id="navLinks">
                <a href="#home" class="active">HOME</a>
                <a href="#game">GAME</a>
                <a href="#about">ABOUT</a>
                <a href="#skills">SKILLS</a>
                <a href="#projects">PROJECTS</a>
                <a href="#resume">RESUME</a>
                <a href="#contact">CONTACT</a>

                <button class="theme-button" id="themeButton" type="button" aria-label="Change theme">
                    ◐
                </button>
            </div>
        </nav>
    </header>

    <main>
        <section class="hero section" id="home">
            <div class="container hero-grid">
                <div class="hero-content reveal">
                    <p class="small-label">PERSONAL PORTFOLIO</p>

                    <h1>CHER<br> MICOLE P.<br> LIRIO</h1>

                    <p class="hero-title">BS INFORMATION TECHNOLOGY STUDENT</p>

                    <p class="hero-description">
                        I create clean, responsive, and functional websites using
                        HTML, CSS, JavaScript, PHP, and MySQL.
                    </p>

                    <div class="hero-buttons">
                        <a href="#game" class="button primary-button">PLAY GAME</a>
                        <a href="#resume" class="button outline-button">VIEW RESUME</a>
                    </div>
                </div>

                <div class="profile-card reveal">
                    <div class="profile-card-top">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>

                    <div class="profile-card-body">
                        <div class="profile-photo-frame">
                            <img
                                src="data:image/jpeg;base64,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"
                                alt="Cher Micole P. Lirio"
                                class="profile-photo">
                        </div>

                        <div class="profile-card-info">
                            <p class="profile-name">CHER MICOLE P. LIRIO</p>
                            <p>BS Information Technology Student</p>
                            <p>Aspiring Web and System Developer</p>

                            <div class="profile-tags">
                                <span>WEB DEVELOPMENT</span>
                                <span>PHP &amp; MYSQL</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section game-section" id="game">
            <div class="container">
                <div class="section-heading reveal">
                    <p class="small-label">INTERACTIVE NAVIGATION</p>
                    <h2>SNAKE GAME</h2>
                    <p class="section-description">
                        Move the snake using the arrow keys or WASD. Enter a category
                        box to open that section.
                    </p>
                </div>

                <div class="game-box reveal">
                    <div class="game-top">
                        <div class="score-box">
                            SCORE:
                            <strong id="score">0</strong>
                        </div>

                        <div id="gameMessage">PRESS ARROW KEYS OR WASD</div>

                        <div class="game-actions">
                            <button id="pauseButton" type="button">PAUSE</button>
                            <button id="restartButton" type="button">RESTART</button>
                        </div>
                    </div>

                    <div class="canvas-holder">
                        <canvas id="snakeCanvas" width="960" height="520"></canvas>
                    </div>

                    <div class="mobile-controls">
                        <button data-direction="up" type="button">▲</button>

                        <div>
                            <button data-direction="left" type="button">◀</button>
                            <button id="mobilePause" type="button">●</button>
                            <button data-direction="right" type="button">▶</button>
                        </div>

                        <button data-direction="down" type="button">▼</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="section" id="about">
            <div class="container">
                <div class="section-heading reveal">
                    <p class="small-label">GET TO KNOW ME</p>
                    <h2>ABOUT ME</h2>
                </div>

                <div class="about-layout">
                    <article class="about-card reveal">
                        <h3>Hello, I’m Cher.</h3>

                        <p>
                            I am a Bachelor of Science in Information Technology student
                            at La Consolacion College Tanauan. I am interested in web
                            development, system design, and creating simple solutions for
                            real-life tasks.
                        </p>

                        <p>
                            I enjoy learning new technologies and improving my skills in
                            both front-end and back-end development.
                        </p>
                    </article>

                    <div class="about-grid reveal">
                        <article class="info-card">
                            <strong>BSIT</strong>
                            <span>COURSE</span>
                        </article>

                        <article class="info-card">
                            <strong>WEB</strong>
                            <span>FOCUS</span>
                        </article>

                        <article class="info-card">
                            <strong>UI</strong>
                            <span>DESIGN</span>
                        </article>

                        <article class="info-card">
                            <strong>DEV</strong>
                            <span>DEVELOPMENT</span>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="section alternate-section" id="skills">
            <div class="container">
                <div class="section-heading reveal">
                    <p class="small-label">WHAT I USE</p>
                    <h2>SKILLS</h2>
                </div>

                <div class="skills-grid">
                    <article class="skill-card reveal">
                        <div class="skill-number">01</div>
                        <h3>HTML & CSS</h3>
                        <p>
                            Creating organized page structures, responsive layouts,
                            animations, and clean user interfaces.
                        </p>
                    </article>

                    <article class="skill-card reveal">
                        <div class="skill-number">02</div>
                        <h3>JavaScript</h3>
                        <p>
                            Adding interactions, navigation, validation, games,
                            and dynamic website functions.
                        </p>
                    </article>

                    <article class="skill-card reveal">
                        <div class="skill-number">03</div>
                        <h3>PHP & MySQL</h3>
                        <p>
                            Building forms, CRUD functions, login systems,
                            databases, and basic web applications.
                        </p>
                    </article>

                    <article class="skill-card reveal">
                        <div class="skill-number">04</div>
                        <h3>UI Design</h3>
                        <p>
                            Designing simple, readable, and responsive interfaces
                            for desktop and mobile devices.
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section" id="projects">
            <div class="container">
                <div class="section-heading reveal">
                    <p class="small-label">SELECTED WORKS</p>
                    <h2>PROJECTS</h2>
                </div>

                <div class="project-filters reveal">
                    <button class="filter active" data-filter="all" type="button">ALL</button>
                    <button class="filter" data-filter="one" type="button">PORTFOLIO</button>
                    <button class="filter" data-filter="two" type="button">RESERVATION</button>
                    <button class="filter" data-filter="three" type="button">MANAGEMENT</button>
                </div>

                <div class="project-grid">
                    <article class="project-card reveal" data-category="one">
                        <div class="project-image project-one">
                            <span>01</span>
                        </div>

                        <div class="project-body">
                            <p class="project-type">PERSONAL WEBSITE</p>
                            <h3>Interactive Portfolio</h3>
                            <p>
                                A responsive portfolio with smooth transitions,
                                resume sections, dark mode, and a Snake navigation game.
                            </p>

                            <div class="project-tools">
                                <span>HTML</span>
                                <span>CSS</span>
                                <span>JAVASCRIPT</span>
                            </div>

                            <button class="project-open" data-project="1" type="button">
                                VIEW DETAILS
                            </button>
                        </div>
                    </article>

                    <article class="project-card reveal" data-category="two">
                        <div class="project-image project-two">
                            <span>02</span>
                        </div>

                        <div class="project-body">
                            <p class="project-type">WEB SYSTEM</p>
                            <h3>Reservation System</h3>
                            <p>
                                A system concept for managing customer reservations,
                                availability, schedules, and payment records.
                            </p>

                            <div class="project-tools">
                                <span>PHP</span>
                                <span>MYSQL</span>
                                <span>JAVASCRIPT</span>
                            </div>

                            <button class="project-open" data-project="2" type="button">
                                VIEW DETAILS
                            </button>
                        </div>
                    </article>

                    <article class="project-card reveal" data-category="three">
                        <div class="project-image project-three">
                            <span>03</span>
                        </div>

                        <div class="project-body">
                            <p class="project-type">MANAGEMENT SYSTEM</p>
                            <h3>Laundry Management</h3>
                            <p>
                                A system concept for customer transactions,
                                services, inventory, receipts, and reports.
                            </p>

                            <div class="project-tools">
                                <span>PHP</span>
                                <span>MYSQL</span>
                                <span>CRUD</span>
                            </div>

                            <button class="project-open" data-project="3" type="button">
                                VIEW DETAILS
                            </button>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="section alternate-section" id="resume">
            <div class="container">
                <div class="resume-heading reveal">
                    <div class="section-heading">
                        <p class="small-label">MY BACKGROUND</p>
                        <h2>RESUME</h2>
                    </div>

                    <button class="button primary-button" id="printResume" type="button">
                        PRINT / SAVE PDF
                    </button>
                </div>

                <article class="resume-template reveal" id="resumeTemplate">
                    <div class="resume-top">
                        <div class="resume-photo">
                            <img
                                src="data:image/jpeg;base64,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"
                                alt="Cher Micole P. Lirio">
                        </div>

                        <div>
                            <h3>CHER MICOLE P. LIRIO</h3>
                            <p>BS Information Technology Student</p>
                            <span>Philippines</span>
                        </div>
                    </div>

                    <div class="resume-columns">
                        <aside>
                            <div class="resume-block">
                                <h4>CONTACT</h4>
                                <p>liriocher25@gmail.com</p>
                                <p>09764332931</p>
                                <p>Philippines</p>
                            </div>

                            <div class="resume-block">
                                <h4>TECHNICAL SKILLS</h4>
                                <ul>
                                    <li>HTML and CSS</li>
                                    <li>JavaScript</li>
                                    <li>PHP and MySQL</li>
                                    <li>Responsive Web Design</li>
                                    <li>CRUD Operations</li>
                                </ul>
                            </div>

                            <div class="resume-block">
                                <h4>TOOLS</h4>
                                <ul>
                                    <li>Visual Studio Code</li>
                                    <li>Laragon</li>
                                    <li>phpMyAdmin</li>
                                    <li>GitHub</li>
                                    <li>Canva</li>
                                    <li>Figma</li>
                                </ul>
                            </div>
                        </aside>

                        <div class="resume-main">
                            <div class="resume-block">
                                <h4>PROFILE</h4>
                                <p>
                                    BS Information Technology student interested in
                                    web development, database systems, and responsive
                                    interface design. Willing to learn and improve through
                                    academic and personal projects.
                                </p>
                            </div>

                            <div class="resume-block">
                                <h4>EDUCATION</h4>

                                <div class="resume-item">
                                    <p class="resume-label">PRIMARY EDUCATION</p>
                                    <h5>Lilyrose School</h5>
                                    <p>Primary Level</p>
                                </div>

                                <div class="resume-item">
                                    <p class="resume-label">SECONDARY EDUCATION</p>
                                    <h5>La Consolacion College Tanauan</h5>
                                    <p>Secondary Level</p>
                                </div>

                                <div class="resume-item">
                                    <p class="resume-label">TERTIARY</p>
                                    <h5>Bachelor of Science in Information Technology</h5>
                                    <p>La Consolacion College Tanauan</p>
                                </div>

                                
                            </div>

                            <div class="resume-block">
                                <h4>PROJECT EXPERIENCE</h4>

                                <div class="resume-item">
                                    <p class="resume-label">WEB DEVELOPMENT</p>
                                    <h5>Interactive Portfolio Website</h5>
                                    <p>
                                        Designed a responsive portfolio with sections,
                                        animations, dark mode, and an interactive game.
                                    </p>
                                </div>

                                <div class="resume-item">
                                    <p class="resume-label">SYSTEM DEVELOPMENT</p>
                                    <h5>Resort and Reservation Management Systems</h5>
                                    <p>
                                        Practiced creating forms, databases,
                                        reports, and responsive admin interfaces.
                                    </p>
                                </div>

                                <div class="resume-item">
                                    <p class="resume-label">SYSTEM DEVELOPMENT</p>
                                    <h5>Laundry Management System</h5>
                                    <p>
                                        Developed a Laundry Management System that manages
                                        customer information, laundry transactions, services,
                                        inventory, payments, receipts, and sales reports. The
                                        system also generates a QR code that customers can scan
                                        to track the current status of their laundry.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section class="section" id="contact">
            <div class="container contact-layout">
                <div class="contact-information reveal">
                    <p class="small-label">GET IN TOUCH</p>
                    <h2>CONTACT</h2>

                    <p>
                        Feel free to contact me for collaborations, website projects, system development, or other opportunities. 
                        You may send a message through the form, and I will respond as soon as possible.
                    </p>

                    <div class="contact-details">
                        <div>
                            <span>EMAIL</span>
                            <strong>liriocher25@gmail.com</strong>
                        </div>

                        <div>
                            <span>LOCATION</span>
                            <strong>Tanauan City, Batangas, Philippines</strong>
                        </div>
                    </div>
                </div>

                <form
                    class="contact-form reveal"
                    id="contactForm"
                    method="post"
                    action="index.php#contact">

                    <input
                        type="hidden"
                        name="action"
                        value="save_contact_message">

                    <div
                        aria-hidden="true"
                        style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;">
                        <label>
                            WEBSITE
                            <input
                                type="text"
                                name="website"
                                tabindex="-1"
                                autocomplete="off">
                        </label>
                    </div>

                    <div class="form-row">
                        <label>
                            NAME
                            <input
                                type="text"
                                id="contactName"
                                name="name"
                                maxlength="120"
                                autocomplete="name"
                                required>
                        </label>

                        <label>
                            EMAIL
                            <input
                                type="email"
                                id="contactEmail"
                                name="email"
                                maxlength="190"
                                autocomplete="email"
                                required>
                        </label>
                    </div>

                    <label>
                        SUBJECT
                        <input
                            type="text"
                            id="contactSubject"
                            name="subject"
                            maxlength="190"
                            required>
                    </label>

                    <label>
                        MESSAGE
                        <textarea
                            id="contactMessage"
                            name="message"
                            rows="6"
                            maxlength="5000"
                            required></textarea>
                    </label>

                    <button
                        class="button primary-button"
                        id="contactSubmitButton"
                        type="submit">
                        SEND MESSAGE
                    </button>
                </form>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container footer-content">
            <span>CHER MICOLE P. LIRIO</span>
            <span>PORTFOLIO</span>
            <a href="#home">↑</a>
        </div>
    </footer>


    <div class="resume-pdf-modal" id="resumePdfModal" aria-hidden="true">
        <div class="resume-pdf-backdrop" data-close-resume-pdf></div>

        <section class="resume-pdf-dialog"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="resumePdfTitle">

            <div class="resume-pdf-toolbar">
                <div>
                    <p class="small-label">RESUME PREVIEW</p>
                    <h2 id="resumePdfTitle">CHER MICOLE P. LIRIO</h2>
                    <span class="resume-pdf-toolbar-description">
                        Review your styled A4 resume before printing or saving it as PDF.
                    </span>
                </div>

                <div class="resume-pdf-actions">
                    <button class="button primary-button"
                            id="confirmResumePdf"
                            type="button">
                        PRINT / SAVE PDF
                    </button>

                    <button class="button outline-button"
                            data-close-resume-pdf
                            type="button">
                        CLOSE
                    </button>
                </div>
            </div>

            <div class="resume-pdf-scroll">
                <div class="resume-pdf-paper" id="resumePdfPaper"></div>
            </div>
        </section>
    </div>

    <div class="modal" id="projectModal" aria-hidden="true">
        <div class="modal-background" data-close-modal></div>

        <article class="modal-card">
            <button class="modal-close" data-close-modal type="button">×</button>

            <p class="small-label" id="modalLabel">PROJECT</p>
            <h2 id="modalTitle">PROJECT TITLE</h2>
            <p id="modalDescription"></p>

            <div class="modal-tools" id="modalTools"></div>
        </article>
    </div>

    <div class="toast" id="toast">READY</div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const originalPrintButton = document.getElementById("printResume");
            const resumeSource = document.getElementById("resumeTemplate");
            const resumeModal = document.getElementById("resumePdfModal");
            const resumePaper = document.getElementById("resumePdfPaper");
            const confirmPrintButton = document.getElementById("confirmResumePdf");
            const closeButtons = document.querySelectorAll("[data-close-resume-pdf]");

            if (!originalPrintButton || !resumeSource ||
                !resumeModal || !resumePaper || !confirmPrintButton) {
                return;
            }

            function buildResumePreview() {
                const clone = resumeSource.cloneNode(true);

                clone.removeAttribute("id");
                clone.classList.remove("reveal");
                clone.classList.add("resume-print-document");

                resumePaper.innerHTML = "";
                resumePaper.appendChild(clone);
            }

            function openResumeModal() {
                buildResumePreview();

                resumeModal.classList.add("open");
                resumeModal.setAttribute("aria-hidden", "false");
                document.body.classList.add("modal-open");

                window.setTimeout(function () {
                    confirmPrintButton.focus();
                }, 50);
            }

            function closeResumeModal() {
                resumeModal.classList.remove("open");
                resumeModal.setAttribute("aria-hidden", "true");
                document.body.classList.remove("modal-open");
            }

            /*
             * Capture mode runs before the old script.js click handler.
             * It prevents window.print() from opening before the modal.
             */
            originalPrintButton.addEventListener(
                "click",
                function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    openResumeModal();
                },
                true
            );

            confirmPrintButton.addEventListener("click", function () {
                buildResumePreview();

                window.requestAnimationFrame(function () {
                    window.print();
                });
            });

            closeButtons.forEach(function (button) {
                button.addEventListener("click", closeResumeModal);
            });

            document.addEventListener("keydown", function (event) {
                if (event.key === "Escape" &&
                    resumeModal.classList.contains("open")) {
                    closeResumeModal();
                }
            });

            window.addEventListener("beforeprint", buildResumePreview);

            window.addEventListener("afterprint", function () {
                /*
                 * Keep the modal open so the user can review again
                 * or save another PDF copy.
                 */
            });
        });
    </script>

</body>
</html>