/*
 * Public maps (Leaflet). Two kinds, chosen by data-map on the container:
 *   area: the listing page. A circle round the public point, never a pin on the house,
 *         because that point is moved 250 to 350 m from the real address.
 *   pins: island, resort and search pages. Pins for the homes in view, fetched from
 *         /api/map whenever the map stops moving.
 * The map is extra: every home on it is also in the list on the page.
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import '../css/map.css';

const BALEARICS = [[38.6, 1.1], [40.1, 4.4]];

function baseMap(el) {
    const map = L.map(el, { scrollWheelZoom: false, keyboard: true });
    L.tileLayer(el.dataset.tiles, {
        attribution: el.dataset.attribution,
        maxZoom: 18,
        minZoom: 8,
    }).addTo(map);
    map.setMaxBounds([[38.0, 0.5], [40.6, 5.0]]);

    // Zoom with the scroll wheel only after the map has been clicked, so the page still scrolls.
    map.on('click focus', () => map.scrollWheelZoom.enable());
    map.on('blur mouseout', () => map.scrollWheelZoom.disable());

    return map;
}

function areaMap(el) {
    const lat = parseFloat(el.dataset.lat);
    const lng = parseFloat(el.dataset.lng);
    const radius = parseInt(el.dataset.radius, 10) || 400;
    const map = baseMap(el);
    map.setView([lat, lng], 15); // the circle's bounds need a view first
    const circle = L.circle([lat, lng], {
        radius,
        color: getComputedStyle(document.documentElement).getPropertyValue('--colour-brand').trim() || '#0b4f6c',
        weight: 2,
        fillOpacity: 0.2,
        interactive: false,
    }).addTo(map);

    // Admin review only: the exact location, which visitors never see.
    if (el.dataset.exactLat && el.dataset.exactLng) {
        const exact = [parseFloat(el.dataset.exactLat), parseFloat(el.dataset.exactLng)];
        L.circleMarker(exact, { radius: 6, color: '#111', weight: 2, fillColor: '#111', fillOpacity: 1, interactive: false }).addTo(map);
        map.fitBounds(circle.getBounds().extend(exact), { padding: [40, 40], maxZoom: 15 });
        return;
    }
    map.fitBounds(circle.getBounds(), { padding: [40, 40], maxZoom: 15 });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function pinsMap(el) {
    const map = baseMap(el);
    const layer = L.layerGroup().addTo(map);
    const status = document.getElementById(el.dataset.status);
    const api = el.dataset.api;
    const query = el.dataset.query || '';
    let markers = {};
    try {
        markers = JSON.parse(el.dataset.markers || '{}');
    } catch {
        markers = {};
    }
    let request = 0;

    if (el.dataset.lat && el.dataset.lng) {
        map.setView([parseFloat(el.dataset.lat), parseFloat(el.dataset.lng)], parseInt(el.dataset.zoom, 10) || 10);
    } else {
        map.fitBounds(BALEARICS);
    }

    async function load() {
        const id = ++request;
        const b = map.getBounds();
        const bbox = [b.getSouth(), b.getWest(), b.getNorth(), b.getEast()].map((n) => n.toFixed(5)).join(',');
        const sep = query ? '&' : '';
        let data;
        try {
            const res = await fetch(`${api}?${query}${sep}bbox=${bbox}`, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(res.status);
            data = await res.json();
        } catch {
            if (status) status.textContent = el.dataset.textError;
            return;
        }
        if (id !== request) return; // a newer request has started

        layer.clearLayers();
        for (const pin of data.pins) {
            // Marker per kind (affiliate, free, paid), set on the Appearance page.
            // Colours are checked as #rrggbb on the server, so they are safe in a style.
            const style = markers[pin.kind] || markers.free || {};
            const colour = /^#[0-9a-f]{6}$/i.test(style.colour || '') ? style.colour : '';
            const icon = style.icon ? `<img src="${escapeHtml(style.icon)}" alt="" width="18" height="18">` : '';
            const kindLabel = style.label ? ` (${style.label})` : '';
            const label = (pin.price ? `${pin.title}, ${pin.price}` : pin.title) + kindLabel;
            const marker = L.marker([pin.lat, pin.lng], {
                title: label,
                alt: label,
                keyboard: true,
                zIndexOffset: { paid: 200, affiliate: 100 }[pin.kind] || 0,
                icon: L.divIcon({
                    className: `jih-pin jih-pin-${escapeHtml(pin.kind)}`,
                    html: `<span${colour ? ` style="background:${colour}"` : ''}>${icon}${escapeHtml(pin.price || '•')}</span>`,
                    iconSize: null,
                }),
            });
            const image = pin.image
                ? `<img src="${escapeHtml(pin.image)}" alt="" width="200" height="150" loading="lazy">`
                : '';
            marker.bindPopup(
                `<a href="${escapeHtml(pin.url)}" class="jih-popup">${image}<strong>${escapeHtml(pin.title)}</strong>`
                + `<span>${escapeHtml(pin.place)}</span>${pin.price ? `<span>${escapeHtml(el.dataset.textFrom.replace(':price', pin.price))}</span>` : ''}`
                + `${style.label ? `<em>${escapeHtml(style.label)}</em>` : ''}</a>`,
            );
            marker.addTo(layer);
        }

        if (status) {
            status.textContent = data.more
                ? el.dataset.textMore.replace(':shown', data.pins.length).replace(':total', data.total)
                : el.dataset.textCount.replace(':count', data.total);
        }
    }

    map.on('moveend', load);
    load();
}

document.querySelectorAll('[data-map]').forEach((el) => {
    if (el.dataset.map === 'area') areaMap(el);
    if (el.dataset.map === 'pins') pinsMap(el);
});
