"use strict";

const $ = (selector, parent = document) => parent.querySelector(selector);
const $$ = (selector, parent = document) => [...parent.querySelectorAll(selector)];

/* HEADER AND MOBILE MENU */
const header = $("#header");
const menuButton = $("#menuButton");
const navLinks = $("#navLinks");

window.addEventListener("scroll", () => {
    header.classList.toggle("scrolled", window.scrollY > 15);
});

menuButton.addEventListener("click", () => {
    const isOpen = navLinks.classList.toggle("open");
    menuButton.classList.toggle("active", isOpen);
    document.body.classList.toggle("menu-open", isOpen);
});

$$(".nav-links a").forEach((link) => {
    link.addEventListener("click", () => {
        navLinks.classList.remove("open");
        menuButton.classList.remove("active");
        document.body.classList.remove("menu-open");
    });
});

/* ACTIVE NAVIGATION */
const sections = $$("main section[id]");
const navigationItems = $$(".nav-links a");

const navigationObserver = new IntersectionObserver(
    (entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;

            navigationItems.forEach((item) => {
                item.classList.toggle(
                    "active",
                    item.getAttribute("href") === `#${entry.target.id}`
                );
            });
        });
    },
    {
        rootMargin: "-35% 0px -55% 0px"
    }
);

sections.forEach((section) => navigationObserver.observe(section));

/* THEME */
const themeButton = $("#themeButton");
const savedTheme = localStorage.getItem("cherPortfolioTheme");

if (savedTheme === "light") {
    document.body.classList.add("light-mode");
}

themeButton.addEventListener("click", () => {
    const isLight = document.body.classList.toggle("light-mode");
    localStorage.setItem("cherPortfolioTheme", isLight ? "light" : "dark");
});

/* REVEAL ANIMATION */
const revealObserver = new IntersectionObserver(
    (entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add("visible");
                revealObserver.unobserve(entry.target);
            }
        });
    },
    { threshold: 0.12 }
);

$$(".reveal").forEach((element) => revealObserver.observe(element));

/* PROJECT FILTER */
const filterButtons = $$(".filter");
const projectCards = $$(".project-card");

filterButtons.forEach((button) => {
    button.addEventListener("click", () => {
        const selected = button.dataset.filter;

        filterButtons.forEach((item) => item.classList.remove("active"));
        button.classList.add("active");

        projectCards.forEach((card) => {
            const visible =
                selected === "all" ||
                card.dataset.category === selected;

            card.classList.toggle("hide", !visible);
        });
    });
});

/* PROJECT MODAL */
const projectInformation = {
    1: {
        label: "PERSONAL WEBSITE",
        title: "Interactive Portfolio",
        description:
            "A responsive personal portfolio with dark mode, animated sections, project filtering, a printable resume, and a Snake category navigation game.",
        tools: ["HTML", "CSS", "JavaScript"]
    },
    2: {
        label: "WEB SYSTEM",
        title: "Reservation System",
        description:
            "A reservation system concept that manages schedules, availability, customer details, bookings, payments, and reports.",
        tools: ["PHP", "MySQL", "JavaScript"]
    },
    3: {
        label: "MANAGEMENT SYSTEM",
        title: "Laundry Management",
        description:
            "A management system concept for recording customers, laundry services, transactions, inventory, receipts, and reports.",
        tools: ["PHP", "MySQL", "CRUD"]
    }
};

const projectModal = $("#projectModal");
const modalLabel = $("#modalLabel");
const modalTitle = $("#modalTitle");
const modalDescription = $("#modalDescription");
const modalTools = $("#modalTools");

function openProjectModal(projectNumber) {
    const project = projectInformation[projectNumber];
    if (!project) return;

    modalLabel.textContent = project.label;
    modalTitle.textContent = project.title;
    modalDescription.textContent = project.description;
    modalTools.innerHTML = project.tools
        .map((tool) => `<span>${tool}</span>`)
        .join("");

    projectModal.classList.add("open");
    projectModal.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
}

function closeProjectModal() {
    projectModal.classList.remove("open");
    projectModal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");
}

$$(".project-open").forEach((button) => {
    button.addEventListener("click", () => {
        openProjectModal(button.dataset.project);
    });
});

$$("[data-close-modal]").forEach((element) => {
    element.addEventListener("click", closeProjectModal);
});

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
        closeProjectModal();
    }
});

/* RESUME PRINT */
$("#printResume").addEventListener("click", () => {
    window.print();
});

/* CONTACT FORM - SAVES TO MYSQL DATABASE */
const toast = $("#toast");
let toastTimer;

function showToast(message) {
    if (!toast) return;

    toast.textContent = message;
    toast.classList.add("show");

    clearTimeout(toastTimer);

    toastTimer = setTimeout(() => {
        toast.classList.remove("show");
    }, 3000);
}

const contactForm = $("#contactForm");
const contactSubmitButton = $("#contactSubmitButton");

contactForm?.addEventListener("submit", async (event) => {
    event.preventDefault();

    const name = $("#contactName").value.trim();
    const email = $("#contactEmail").value.trim();
    const subject = $("#contactSubject").value.trim();
    const message = $("#contactMessage").value.trim();

    if (!name || !email || !subject || !message) {
        showToast("COMPLETE ALL FIELDS");
        return;
    }

    const originalButtonText = contactSubmitButton.textContent;

    contactSubmitButton.disabled = true;
    contactSubmitButton.textContent = "SENDING...";

    try {
        const response = await fetch("index.php", {
            method: "POST",
            body: new FormData(contactForm),
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(
                result.message || "Unable to save your message."
            );
        }

        contactForm.reset();
        showToast("MESSAGE SAVED SUCCESSFULLY");
    } catch (error) {
        showToast(
            error.message ||
            "DATABASE ERROR. OPEN SETUP.PHP FIRST."
        );
    } finally {
        contactSubmitButton.disabled = false;
        contactSubmitButton.textContent = originalButtonText;
    }
});

/* ======================================================
   SNAKE CATEGORY GAME
   ====================================================== */

const canvas = $("#snakeCanvas");
const context = canvas.getContext("2d");

const scoreText = $("#score");
const gameMessage = $("#gameMessage");
const pauseButton = $("#pauseButton");
const restartButton = $("#restartButton");
const mobilePause = $("#mobilePause");

const CELL = 20;
const COLUMNS = canvas.width / CELL;
const ROWS = canvas.height / CELL;
const SPEED = 115;

const portals = [
    {
        id: "about",
        label: "ABOUT",
        x: 2,
        y: 2,
        width: 8,
        height: 5,
        color: "#6ff0bf"
    },
    {
        id: "skills",
        label: "SKILLS",
        x: 20,
        y: 2,
        width: 8,
        height: 5,
        color: "#77a4ff"
    },
    {
        id: "projects",
        label: "PROJECTS",
        x: 37,
        y: 2,
        width: 9,
        height: 5,
        color: "#c38dff"
    },
    {
        id: "resume",
        label: "RESUME",
        x: 7,
        y: 19,
        width: 9,
        height: 5,
        color: "#ffca6a"
    },
    {
        id: "contact",
        label: "CONTACT",
        x: 33,
        y: 19,
        width: 10,
        height: 5,
        color: "#ff7f8d"
    }
];

let snake = [];
let direction = { x: 0, y: 0 };
let nextDirection = { x: 0, y: 0 };
let chip = { x: 14, y: 12 };
let score = 0;
let started = false;
let paused = false;
let movingToSection = false;
let gameLoop;

function resetGame() {
    snake = [
        { x: 24, y: 13 },
        { x: 23, y: 13 },
        { x: 22, y: 13 },
        { x: 21, y: 13 }
    ];

    direction = { x: 0, y: 0 };
    nextDirection = { x: 0, y: 0 };
    score = 0;
    started = false;
    paused = false;
    movingToSection = false;

    scoreText.textContent = "0";
    gameMessage.textContent = "PRESS ARROW KEYS OR WASD";
    pauseButton.textContent = "PAUSE";

    clearInterval(gameLoop);
    gameLoop = setInterval(updateGame, SPEED);

    placeChip();
    drawGame();
}

function pointInsidePortal(x, y) {
    return portals.some((portal) => {
        return (
            x >= portal.x &&
            x < portal.x + portal.width &&
            y >= portal.y &&
            y < portal.y + portal.height
        );
    });
}

function pointOnSnake(x, y) {
    return snake.some((part) => part.x === x && part.y === y);
}

function placeChip() {
    let valid = false;

    while (!valid) {
        chip = {
            x: Math.floor(Math.random() * COLUMNS),
            y: Math.floor(Math.random() * ROWS)
        };

        valid =
            !pointInsidePortal(chip.x, chip.y) &&
            !pointOnSnake(chip.x, chip.y);
    }
}

function setDirection(newDirection) {
    if (movingToSection) return;

    const opposite =
        direction.x + newDirection.x === 0 &&
        direction.y + newDirection.y === 0 &&
        (direction.x !== 0 || direction.y !== 0);

    if (opposite) return;

    nextDirection = newDirection;
    started = true;
    paused = false;
    gameMessage.textContent = "ENTER A CATEGORY";
    pauseButton.textContent = "PAUSE";
}

function updateGame() {
    if (!started || paused || movingToSection) {
        drawGame();
        return;
    }

    direction = nextDirection;

    const newHead = {
        x: snake[0].x + direction.x,
        y: snake[0].y + direction.y
    };

    const hitWall =
        newHead.x < 0 ||
        newHead.x >= COLUMNS ||
        newHead.y < 0 ||
        newHead.y >= ROWS;

    const hitSnake = snake.some((part, index) => {
        return (
            index > 0 &&
            part.x === newHead.x &&
            part.y === newHead.y
        );
    });

    if (hitWall || hitSnake) {
        gameOver();
        return;
    }

    snake.unshift(newHead);

    if (newHead.x === chip.x && newHead.y === chip.y) {
        score += 10;
        scoreText.textContent = String(score);
        placeChip();
    } else {
        snake.pop();
    }

    const portal = portals.find((item) => {
        return (
            newHead.x >= item.x &&
            newHead.x < item.x + item.width &&
            newHead.y >= item.y &&
            newHead.y < item.y + item.height
        );
    });

    if (portal) {
        openSection(portal);
    }

    drawGame();
}

function gameOver() {
    paused = true;
    started = false;
    direction = { x: 0, y: 0 };
    nextDirection = { x: 0, y: 0 };
    gameMessage.textContent = "GAME OVER";
    pauseButton.textContent = "CONTINUE";
    drawGame(true);
}

function openSection(portal) {
    movingToSection = true;
    paused = true;
    gameMessage.textContent = `OPENING ${portal.label}`;

    setTimeout(() => {
        document.getElementById(portal.id).scrollIntoView({
            behavior: "smooth",
            block: "start"
        });

        movingToSection = false;
        gameMessage.textContent = `${portal.label} OPENED`;
        pauseButton.textContent = "CONTINUE";
    }, 450);
}

function togglePause() {
    if (!started) {
        started = true;

        if (nextDirection.x === 0 && nextDirection.y === 0) {
            nextDirection = { x: 1, y: 0 };
        }
    }

    paused = !paused;
    pauseButton.textContent = paused ? "CONTINUE" : "PAUSE";
    gameMessage.textContent = paused ? "PAUSED" : "ENTER A CATEGORY";
}

function roundedRectangle(x, y, width, height, radius) {
    context.beginPath();
    context.moveTo(x + radius, y);
    context.arcTo(x + width, y, x + width, y + height, radius);
    context.arcTo(x + width, y + height, x, y + height, radius);
    context.arcTo(x, y + height, x, y, radius);
    context.arcTo(x, y, x + width, y, radius);
    context.closePath();
}

function drawGrid() {
    context.strokeStyle = "rgba(111, 240, 191, 0.035)";
    context.lineWidth = 1;

    for (let x = 0; x <= canvas.width; x += CELL) {
        context.beginPath();
        context.moveTo(x, 0);
        context.lineTo(x, canvas.height);
        context.stroke();
    }

    for (let y = 0; y <= canvas.height; y += CELL) {
        context.beginPath();
        context.moveTo(0, y);
        context.lineTo(canvas.width, y);
        context.stroke();
    }
}

function drawPortal(portal) {
    const x = portal.x * CELL;
    const y = portal.y * CELL;
    const width = portal.width * CELL;
    const height = portal.height * CELL;

    context.save();

    context.globalAlpha = 0.13;
    context.fillStyle = portal.color;
    roundedRectangle(x + 5, y + 5, width - 10, height - 10, 14);
    context.fill();

    context.globalAlpha = 0.8;
    context.strokeStyle = portal.color;
    context.lineWidth = 2;
    context.setLineDash([7, 7]);
    roundedRectangle(x + 5, y + 5, width - 10, height - 10, 14);
    context.stroke();

    context.globalAlpha = 1;
    context.setLineDash([]);
    context.fillStyle = portal.color;
    context.font = "bold 15px Arial";
    context.textAlign = "center";
    context.textBaseline = "middle";
    context.fillText(
        portal.label,
        x + width / 2,
        y + height / 2
    );

    context.restore();
}

function drawChip() {
    context.save();
    context.translate(
        chip.x * CELL + CELL / 2,
        chip.y * CELL + CELL / 2
    );
    context.rotate(Math.PI / 4);
    context.fillStyle = "#6ff0bf";
    context.shadowColor = "#6ff0bf";
    context.shadowBlur = 15;
    context.fillRect(-5, -5, 10, 10);
    context.restore();
}

function drawSnake() {
    snake.forEach((part, index) => {
        const x = part.x * CELL + 2;
        const y = part.y * CELL + 2;
        const size = CELL - 4;

        context.save();

        context.fillStyle =
            index === 0
                ? "#a0ffdc"
                : `rgba(111, 240, 191, ${Math.max(0.35, 0.92 - index * 0.05)})`;

        if (index === 0) {
            context.shadowColor = "#6ff0bf";
            context.shadowBlur = 14;
        }

        roundedRectangle(x, y, size, size, index === 0 ? 7 : 5);
        context.fill();

        context.restore();
    });
}

function drawOverlay(title, subtitle) {
    context.fillStyle = "rgba(4, 10, 18, 0.62)";
    context.fillRect(0, 0, canvas.width, canvas.height);

    context.fillStyle = "#ffffff";
    context.font = "bold 31px Arial";
    context.textAlign = "center";
    context.fillText(title, canvas.width / 2, canvas.height / 2);

    context.fillStyle = "#9eafc4";
    context.font = "13px Arial";
    context.fillText(
        subtitle,
        canvas.width / 2,
        canvas.height / 2 + 28
    );
}

function drawGame(gameOverScreen = false) {
    const gradient = context.createLinearGradient(
        0,
        0,
        canvas.width,
        canvas.height
    );

    gradient.addColorStop(0, "#081423");
    gradient.addColorStop(1, "#0a1a2c");

    context.fillStyle = gradient;
    context.fillRect(0, 0, canvas.width, canvas.height);

    drawGrid();
    portals.forEach(drawPortal);
    drawChip();
    drawSnake();

    if (paused && started && !movingToSection) {
        drawOverlay("PAUSED", "PRESS SPACE OR CONTINUE");
    }

    if (gameOverScreen) {
        drawOverlay("GAME OVER", "PRESS RESTART");
    }
}

function handleKeyboard(event) {
    const activeTag = document.activeElement.tagName.toLowerCase();

    if (
        activeTag === "input" ||
        activeTag === "textarea"
    ) {
        return;
    }

    const directions = {
        ArrowUp: { x: 0, y: -1 },
        w: { x: 0, y: -1 },
        W: { x: 0, y: -1 },
        ArrowDown: { x: 0, y: 1 },
        s: { x: 0, y: 1 },
        S: { x: 0, y: 1 },
        ArrowLeft: { x: -1, y: 0 },
        a: { x: -1, y: 0 },
        A: { x: -1, y: 0 },
        ArrowRight: { x: 1, y: 0 },
        d: { x: 1, y: 0 },
        D: { x: 1, y: 0 }
    };

    if (directions[event.key]) {
        event.preventDefault();
        setDirection(directions[event.key]);
    }

    if (event.code === "Space") {
        event.preventDefault();
        togglePause();
    }
}

document.addEventListener("keydown", handleKeyboard);

pauseButton.addEventListener("click", togglePause);
mobilePause.addEventListener("click", togglePause);
restartButton.addEventListener("click", resetGame);

$$("[data-direction]").forEach((button) => {
    button.addEventListener("click", () => {
        const directions = {
            up: { x: 0, y: -1 },
            down: { x: 0, y: 1 },
            left: { x: -1, y: 0 },
            right: { x: 1, y: 0 }
        };

        setDirection(directions[button.dataset.direction]);
    });
});

resetGame();
