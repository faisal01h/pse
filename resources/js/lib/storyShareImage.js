const WIDTH = 1080;
const HEIGHT = 1920;
const PAD_X = 96;
const INK = '#0b0c0c';
const BRAND = '#f43f5e';
const MUTED = '#a8a29e';
const WHITE = '#ffffff';
const CANVAS = '#f3f2f1';

const FONT = 'ui-sans-serif, system-ui, sans-serif';

export const SHARE_THEMES = [
    { id: 'lyrics-dark', label: 'Lirik gelap' },
    { id: 'lyrics-light', label: 'Lirik terang' },
    { id: 'portrait', label: 'Potret' },
    { id: 'poster', label: 'Poster' },
    { id: 'editorial', label: 'Editorial' },
    { id: 'minimal', label: 'Minimal' },
];

function wrapLines(ctx, text, maxWidth) {
    const words = String(text || '').trim().split(/\s+/).filter(Boolean);
    if (words.length === 0) {
        return [];
    }

    const lines = [];
    let current = words[0];

    for (let i = 1; i < words.length; i += 1) {
        const next = `${current} ${words[i]}`;
        if (ctx.measureText(next).width <= maxWidth) {
            current = next;
        } else {
            lines.push(current);
            current = words[i];
        }
    }

    lines.push(current);
    return lines;
}

function truncateLines(lines, max) {
    if (lines.length <= max) {
        return lines;
    }

    const kept = lines.slice(0, max);
    const last = kept[max - 1];
    kept[max - 1] = last.length > 3 ? `${last.slice(0, Math.max(0, last.length - 1))}…` : '…';
    return kept;
}

function loadImage(url) {
    return new Promise((resolve) => {
        if (!url) {
            resolve(null);
            return;
        }

        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => resolve(img);
        img.onerror = () => resolve(null);
        img.src = url;
    });
}

function lyricOf(record) {
    const lyric = (record.quote || record.summary || '').trim() || 'Catatan publik';
    return record.quote ? `“${lyric}”` : lyric;
}

function nameOf(record) {
    return record.politician?.name ?? 'Tokoh publik';
}

function subtitleOf(record) {
    return [record.politician?.position, record.politician?.institution]
        .filter(Boolean)
        .join(' · ');
}

function dateOf(record) {
    const value = record.occurred_on || record.date;
    if (!value) {
        return null;
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    return parsed.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function appUrlLabel(appUrl) {
    const raw = String(appUrl || '').trim() || (typeof window !== 'undefined' ? window.location.origin : '');
    return raw.replace(/\/$/, '');
}

function drawBlurredPhoto(ctx, photo, alpha = 0.22) {
    if (!photo) {
        return;
    }

    ctx.save();
    ctx.globalAlpha = alpha;
    const scale = Math.max(WIDTH / photo.width, HEIGHT / photo.height);
    const w = photo.width * scale;
    const h = photo.height * scale;
    ctx.filter = 'blur(48px)';
    ctx.drawImage(photo, (WIDTH - w) / 2, (HEIGHT - h) / 2, w, h);
    ctx.restore();
}

function drawCover(ctx, img, { size = 280, y = 200, stroke = 'rgba(255,255,255,0.18)' } = {}) {
    if (!img) {
        return;
    }

    const crop = Math.min(img.width, img.height);
    const sx = (img.width - crop) / 2;
    const sy = (img.height - crop) / 2;
    const x = (WIDTH - size) / 2;

    ctx.drawImage(img, sx, sy, crop, crop, x, y, size, size);
    ctx.strokeStyle = stroke;
    ctx.lineWidth = 3;
    ctx.strokeRect(x, y, size, size);
}

function drawCirclePhoto(ctx, img, x, y, size, fill = '#1c1917', stroke = 'rgba(255,255,255,0.25)') {
    ctx.fillStyle = fill;
    ctx.beginPath();
    ctx.arc(x + size / 2, y + size / 2, size / 2, 0, Math.PI * 2);
    ctx.fill();

    if (img) {
        const crop = Math.min(img.width, img.height);
        const sx = (img.width - crop) / 2;
        const sy = (img.height - crop) / 2;
        ctx.save();
        ctx.beginPath();
        ctx.arc(x + size / 2, y + size / 2, size / 2, 0, Math.PI * 2);
        ctx.clip();
        ctx.drawImage(img, sx, sy, crop, crop, x, y, size, size);
        ctx.restore();
    }

    ctx.strokeStyle = stroke;
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.arc(x + size / 2, y + size / 2, size / 2, 0, Math.PI * 2);
    ctx.stroke();
}

function drawCenteredLines(ctx, lines, { x, y, lineHeight, color, font }) {
    ctx.fillStyle = color;
    ctx.font = font;
    ctx.textAlign = 'center';
    lines.forEach((line, index) => {
        ctx.fillText(line, x, y + index * lineHeight);
    });
}

function drawFooter(ctx, appUrl, color) {
    ctx.fillStyle = color;
    ctx.font = `500 26px ${FONT}`;
    ctx.textAlign = 'center';
    ctx.fillText('Dibagikan dari PolitiSEE', WIDTH / 2, HEIGHT - 120);
    ctx.font = `500 24px ${FONT}`;
    ctx.fillText(appUrlLabel(appUrl), WIDTH / 2, HEIGHT - 78);
}

function drawMetaRow(ctx, { photo, name, subtitle, date, textColor, mutedColor, top }) {
    const avatarSize = 88;
    ctx.textAlign = 'left';
    ctx.font = `700 40px ${FONT}`;
    const nameWidth = ctx.measureText(name).width;
    ctx.font = `500 28px ${FONT}`;
    const subWidth = subtitle ? ctx.measureText(subtitle).width : 0;
    const clusterWidth = avatarSize + 28 + Math.max(nameWidth, subWidth);
    const clusterX = (WIDTH - clusterWidth) / 2;

    drawCirclePhoto(ctx, photo, clusterX, top, avatarSize, mutedColor === MUTED ? '#1c1917' : '#e7e5e4', `${textColor}40`);

    ctx.fillStyle = textColor;
    ctx.font = `700 40px ${FONT}`;
    ctx.fillText(name, clusterX + avatarSize + 28, top + 38);

    if (subtitle) {
        ctx.fillStyle = mutedColor;
        ctx.font = `500 28px ${FONT}`;
        ctx.fillText(subtitle, clusterX + avatarSize + 28, top + 78);
    }

    if (date) {
        ctx.fillStyle = mutedColor;
        ctx.font = `500 26px ${FONT}`;
        ctx.textAlign = 'center';
        ctx.fillText(date, WIDTH / 2, top + avatarSize + 56);
    }
}

function layoutLyrics(ctx, record, photo, { dark, appUrl }) {
    const bgTop = dark ? '#1c1917' : CANVAS;
    const bgMid = dark ? INK : WHITE;
    const bgBot = dark ? '#292524' : '#e7e5e4';
    const text = dark ? WHITE : INK;
    const muted = dark ? MUTED : '#57534e';
    const coverStroke = dark ? 'rgba(255,255,255,0.18)' : 'rgba(11,12,12,0.2)';

    const gradient = ctx.createLinearGradient(0, 0, WIDTH, HEIGHT);
    gradient.addColorStop(0, bgTop);
    gradient.addColorStop(0.55, bgMid);
    gradient.addColorStop(1, bgBot);
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, WIDTH, HEIGHT);

    if (dark) {
        drawBlurredPhoto(ctx, photo, 0.18);
        ctx.fillStyle = 'rgba(11,12,12,0.55)';
        ctx.fillRect(0, 0, WIDTH, HEIGHT);
    }

    ctx.fillStyle = BRAND;
    ctx.fillRect(0, 0, WIDTH, 12);

    drawCover(ctx, photo, { y: 200, stroke: coverStroke });

    const quoted = lyricOf(record);
    ctx.font = `700 64px ${FONT}`;
    const lines = truncateLines(wrapLines(ctx, quoted, WIDTH - PAD_X * 2), 8);
    const lineHeight = 78;
    const blockHeight = lines.length * lineHeight;
    let textY = photo ? 560 : 420;
    if (!photo) {
        textY = (HEIGHT - blockHeight) / 2 - 80;
    }
    drawCenteredLines(ctx, lines, {
        x: WIDTH / 2,
        y: textY,
        lineHeight,
        color: text,
        font: `700 64px ${FONT}`,
    });

    const metaTop = Math.max(textY + blockHeight + 80, 1320);
    drawMetaRow(ctx, {
        photo,
        name: nameOf(record),
        subtitle: subtitleOf(record),
        date: dateOf(record),
        textColor: text,
        mutedColor: muted,
        top: metaTop,
    });

    drawFooter(ctx, appUrl, muted);
}

function layoutPortrait(ctx, record, photo, { appUrl }) {
    ctx.fillStyle = INK;
    ctx.fillRect(0, 0, WIDTH, HEIGHT);

    if (photo) {
        const scale = Math.max(WIDTH / photo.width, HEIGHT / photo.height);
        const w = photo.width * scale;
        const h = photo.height * scale;
        ctx.drawImage(photo, (WIDTH - w) / 2, (HEIGHT - h) / 2, w, h);
    } else {
        const gradient = ctx.createLinearGradient(0, 0, WIDTH, HEIGHT);
        gradient.addColorStop(0, '#292524');
        gradient.addColorStop(1, INK);
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, WIDTH, HEIGHT);
    }

    const veil = ctx.createLinearGradient(0, HEIGHT * 0.35, 0, HEIGHT);
    veil.addColorStop(0, 'rgba(11,12,12,0)');
    veil.addColorStop(0.45, 'rgba(11,12,12,0.55)');
    veil.addColorStop(1, 'rgba(11,12,12,0.92)');
    ctx.fillStyle = veil;
    ctx.fillRect(0, 0, WIDTH, HEIGHT);

    ctx.fillStyle = BRAND;
    ctx.fillRect(0, 0, WIDTH, 12);

    const quoted = lyricOf(record);
    ctx.fillStyle = WHITE;
    ctx.font = `700 58px ${FONT}`;
    ctx.textAlign = 'left';
    const lines = truncateLines(wrapLines(ctx, quoted, WIDTH - PAD_X * 2), 7);
    const lineHeight = 72;
    const textY = HEIGHT - 460 - lines.length * lineHeight;
    lines.forEach((line, index) => {
        ctx.fillText(line, PAD_X, textY + index * lineHeight);
    });

    ctx.fillStyle = WHITE;
    ctx.font = `700 40px ${FONT}`;
    ctx.fillText(nameOf(record), PAD_X, HEIGHT - 280);

    const subtitle = subtitleOf(record);
    if (subtitle) {
        ctx.fillStyle = MUTED;
        ctx.font = `500 28px ${FONT}`;
        ctx.fillText(subtitle, PAD_X, HEIGHT - 230);
    }

    const date = dateOf(record);
    if (date) {
        ctx.fillStyle = MUTED;
        ctx.font = `500 26px ${FONT}`;
        ctx.fillText(date, PAD_X, HEIGHT - 180);
    }

    drawFooter(ctx, appUrl, MUTED);
}

function layoutPoster(ctx, record, photo, { appUrl }) {
    ctx.fillStyle = BRAND;
    ctx.fillRect(0, 0, WIDTH, HEIGHT);

    ctx.fillStyle = INK;
    ctx.fillRect(0, HEIGHT * 0.62, WIDTH, HEIGHT * 0.38);

    if (photo) {
        ctx.save();
        ctx.globalAlpha = 0.2;
        const size = 720;
        const crop = Math.min(photo.width, photo.height);
        const sx = (photo.width - crop) / 2;
        const sy = (photo.height - crop) / 2;
        ctx.drawImage(photo, sx, sy, crop, crop, WIDTH - size + 40, -40, size, size);
        ctx.restore();
    }

    const quoted = lyricOf(record);
    ctx.fillStyle = WHITE;
    ctx.font = `800 72px ${FONT}`;
    ctx.textAlign = 'left';
    const lines = truncateLines(wrapLines(ctx, quoted, WIDTH - PAD_X * 2 - 40), 8);
    const lineHeight = 86;
    lines.forEach((line, index) => {
        ctx.fillText(line, PAD_X, 280 + index * lineHeight);
    });

    if (photo) {
        const size = 200;
        const x = PAD_X;
        const y = HEIGHT - 400;
        const crop = Math.min(photo.width, photo.height);
        const sx = (photo.width - crop) / 2;
        const sy = (photo.height - crop) / 2;
        ctx.drawImage(photo, sx, sy, crop, crop, x, y, size, size);
        ctx.strokeStyle = WHITE;
        ctx.lineWidth = 4;
        ctx.strokeRect(x, y, size, size);

        ctx.fillStyle = WHITE;
        ctx.font = `700 42px ${FONT}`;
        ctx.fillText(nameOf(record), x + size + 28, y + 60);

        const subtitle = subtitleOf(record);
        if (subtitle) {
            ctx.fillStyle = MUTED;
            ctx.font = `500 28px ${FONT}`;
            ctx.fillText(subtitle, x + size + 28, y + 110);
        }

        const date = dateOf(record);
        if (date) {
            ctx.fillStyle = MUTED;
            ctx.font = `500 26px ${FONT}`;
            ctx.fillText(date, x + size + 28, y + 160);
        }
    } else {
        ctx.fillStyle = WHITE;
        ctx.font = `700 42px ${FONT}`;
        ctx.fillText(nameOf(record), PAD_X, HEIGHT - 280);

        const subtitle = subtitleOf(record);
        if (subtitle) {
            ctx.fillStyle = MUTED;
            ctx.font = `500 28px ${FONT}`;
            ctx.fillText(subtitle, PAD_X, HEIGHT - 230);
        }

        const date = dateOf(record);
        if (date) {
            ctx.fillStyle = MUTED;
            ctx.font = `500 26px ${FONT}`;
            ctx.fillText(date, PAD_X, HEIGHT - 180);
        }
    }

    drawFooter(ctx, appUrl, MUTED);
}

function layoutEditorial(ctx, record, photo, { appUrl }) {
    ctx.fillStyle = WHITE;
    ctx.fillRect(0, 0, WIDTH, HEIGHT);

    ctx.fillStyle = BRAND;
    ctx.fillRect(0, 0, WIDTH, 12);

    ctx.fillStyle = BRAND;
    ctx.fillRect(PAD_X, 160, 120, 8);

    const date = dateOf(record);
    if (date) {
        ctx.fillStyle = '#57534e';
        ctx.font = `500 28px ${FONT}`;
        ctx.textAlign = 'left';
        ctx.fillText(date, PAD_X, 230);
    }

    const quoted = lyricOf(record);
    ctx.fillStyle = INK;
    ctx.font = `700 60px ${FONT}`;
    ctx.textAlign = 'left';
    const lines = truncateLines(wrapLines(ctx, quoted, WIDTH - PAD_X * 2 - 24), 8);
    const lineHeight = 76;
    const quoteTop = date ? 320 : 280;
    lines.forEach((line, index) => {
        ctx.fillText(line, PAD_X, quoteTop + index * lineHeight);
    });

    const metaTop = Math.max(quoteTop + lines.length * lineHeight + 100, 1200);
    ctx.fillStyle = CANVAS;
    ctx.fillRect(0, metaTop - 40, WIDTH, HEIGHT - (metaTop - 40));

    drawMetaRow(ctx, {
        photo,
        name: nameOf(record),
        subtitle: subtitleOf(record),
        date: null,
        textColor: INK,
        mutedColor: '#57534e',
        top: metaTop + 40,
    });

    drawFooter(ctx, appUrl, '#57534e');
}

function layoutMinimal(ctx, record, { appUrl }) {
    ctx.fillStyle = INK;
    ctx.fillRect(0, 0, WIDTH, HEIGHT);

    ctx.fillStyle = BRAND;
    ctx.fillRect(PAD_X, 160, 64, 8);

    const date = dateOf(record);
    if (date) {
        ctx.fillStyle = MUTED;
        ctx.font = `500 28px ${FONT}`;
        ctx.textAlign = 'left';
        ctx.fillText(date, PAD_X, 240);
    }

    const quoted = lyricOf(record);
    ctx.fillStyle = WHITE;
    ctx.font = `700 68px ${FONT}`;
    ctx.textAlign = 'left';
    const lines = truncateLines(wrapLines(ctx, quoted, WIDTH - PAD_X * 2), 9);
    const lineHeight = 84;
    const blockHeight = lines.length * lineHeight;
    const textY = (HEIGHT - blockHeight) / 2;
    lines.forEach((line, index) => {
        ctx.fillText(line, PAD_X, textY + index * lineHeight);
    });

    ctx.fillStyle = WHITE;
    ctx.font = `700 36px ${FONT}`;
    ctx.fillText(nameOf(record), PAD_X, HEIGHT - 260);

    const subtitle = subtitleOf(record);
    if (subtitle) {
        ctx.fillStyle = MUTED;
        ctx.font = `500 28px ${FONT}`;
        ctx.fillText(subtitle, PAD_X, HEIGHT - 210);
    }

    drawFooter(ctx, appUrl, MUTED);
}

function renderTheme(ctx, themeId, record, photo, appUrl) {
    const options = { appUrl };

    switch (themeId) {
        case 'lyrics-light':
            layoutLyrics(ctx, record, photo, { dark: false, ...options });
            break;
        case 'portrait':
            layoutPortrait(ctx, record, photo, options);
            break;
        case 'poster':
            layoutPoster(ctx, record, photo, options);
            break;
        case 'editorial':
            layoutEditorial(ctx, record, photo, options);
            break;
        case 'minimal':
            layoutMinimal(ctx, record, options);
            break;
        case 'lyrics-dark':
        default:
            layoutLyrics(ctx, record, photo, { dark: true, ...options });
            break;
    }
}

function resolveAppUrl(appUrl) {
    if (appUrl) {
        return appUrl;
    }

    if (typeof window !== 'undefined') {
        return window.Ziggy?.url || window.location.origin;
    }

    return '';
}

/**
 * @returns {Promise<string>} data URL (PNG)
 */
export async function buildStoryShareImage(record, themeId = 'lyrics-dark', appUrl) {
    const canvas = document.createElement('canvas');
    canvas.width = WIDTH;
    canvas.height = HEIGHT;
    const ctx = canvas.getContext('2d');
    const photo = await loadImage(record.politician?.photo_url);
    renderTheme(ctx, themeId, record, photo, resolveAppUrl(appUrl));
    return canvas.toDataURL('image/png');
}

/**
 * @returns {Promise<Array<{ id: string, label: string, dataUrl: string }>>}
 */
export async function buildAllStoryShareImages(record, appUrl) {
    const photo = await loadImage(record.politician?.photo_url);
    const resolvedUrl = resolveAppUrl(appUrl);

    return SHARE_THEMES.map((theme) => {
        const canvas = document.createElement('canvas');
        canvas.width = WIDTH;
        canvas.height = HEIGHT;
        const ctx = canvas.getContext('2d');
        renderTheme(ctx, theme.id, record, photo, resolvedUrl);
        return {
            id: theme.id,
            label: theme.label,
            dataUrl: canvas.toDataURL('image/png'),
        };
    });
}

export const STORY_SHARE_SIZE = { width: WIDTH, height: HEIGHT };
