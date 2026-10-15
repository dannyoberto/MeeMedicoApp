/**
 * Beacon de contacto: cada clic en un enlace con data-contact (Llamar, WhatsApp, Cómo
 * llegar…) se registra en POST /api/v1/contact-events (DATABASE.md §14.3).
 *
 * Sin React a propósito: la ficha del médico no carga React. Son unas líneas con
 * delegación de eventos y navigator.sendBeacon, que sobrevive a que la página navegue
 * a tel: o a WhatsApp. Si falla, no pasa nada: el enlace funciona igual.
 */
const ENDPOINT = '/api/v1/contact-events';

export function trackContactEvents(root: Document = document): void {
    root.addEventListener(
        'click',
        (event) => {
            const link = (event.target as Element | null)?.closest<HTMLElement>('[data-contact]');
            const { contact, doctor, source } = link?.dataset ?? {};
            if (!contact || !doctor) {
                return;
            }

            const body = JSON.stringify({ doctor, type: contact, source: source ?? 'profile' });

            if (navigator.sendBeacon?.(ENDPOINT, new Blob([body], { type: 'application/json' }))) {
                return;
            }

            void fetch(ENDPOINT, {
                method: 'POST',
                body,
                keepalive: true,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            }).catch(() => undefined);
        },
        { capture: true },
    );
}
