/**
 * Resolve a repo-style emoji shortcode (e.g. ":brain:") to its emoji.
 * Returns the original input if not a shortcode, or null if empty.
 *
 * repo's icon column stores either shortcodes, raw emoji, or icon names
 * (like "Calendar"). We only handle the first two — icon names are passed
 * through and the caller falls back to a default glyph.
 */
const SHORTCODES: Record<string, string> = {
    brain: '🧠',
    frame_photo: '🖼️',
    framed_picture: '🖼️',
    stethoscope: '🩺',
    test_tube: '🧪',
    hospital: '🏥',
    microscope: '🔬',
    gear: '⚙️',
    busts_in_silhouette: '👥',
    bust_in_silhouette: '👤',
    classical_building: '🏛️',
    label: '🏷️',
    rocket: '🚀',
    sparkles: '✨',
    star: '⭐',
    fire: '🔥',
    bug: '🐛',
    book: '📕',
    books: '📚',
    chart_with_upwards_trend: '📈',
    bar_chart: '📊',
    calendar: '📅',
    clipboard: '📋',
    package: '📦',
    pencil: '✏️',
    pencil2: '✏️',
    memo: '📝',
    hammer: '🔨',
    wrench: '🔧',
    lock: '🔒',
    key: '🔑',
    shield: '🛡️',
    target: '🎯',
    bulb: '💡',
    art: '🎨',
    musical_note: '🎵',
    movie_camera: '🎥',
    camera: '📷',
    phone: '📞',
    mobile_phone: '📱',
    computer: '💻',
    cloud: '☁️',
    earth_americas: '🌎',
    earth_asia: '🌏',
    earth_africa: '🌍',
    heart: '❤️',
    blue_heart: '💙',
    green_heart: '💚',
    purple_heart: '💜',
    yellow_heart: '💛',
    sun_with_face: '🌞',
    sun: '☀️',
    moon: '🌙',
    cone: '🚧',
    pill: '💊',
    syringe: '💉',
    dna: '🧬',
    bone: '🦴',
    tooth: '🦷',
    eye: '👁️',
    speech_balloon: '💬',
    bookmark: '🔖',
    paperclip: '📎',
    chains: '⛓️',
    thread: '🧵',
    needle_and_thread: '🪡',
    scroll: '📜',
    page_facing_up: '📄',
    page_with_curl: '📃',
    folder: '📁',
    file_folder: '📁',
    open_file_folder: '📂',
};

export function resolveEmoji(input: string | null | undefined): string | null {
    if (!input) return null;
    const trimmed = input.trim();
    if (!trimmed) return null;

    const m = trimmed.match(/^:([a-z0-9_+-]+):$/i);
    if (m) {
        const key = m[1].toLowerCase();
        return SHORTCODES[key] ?? null;
    }

    // Already an emoji or text — heuristic: if it has any non-ASCII, treat as emoji.
    // eslint-disable-next-line no-control-regex
    if (/[^\x00-\x7F]/.test(trimmed)) return trimmed;

    return null;
}
