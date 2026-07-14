import { Injectable } from '@angular/core';

/** Keeps the selected admin locale stable even when the PSFS session cookie rotates. */
@Injectable({ providedIn: 'root' })
export class AdminLocaleService {
  private static readonly storageKey = 'psfs.admin-v2.locale';

  current(): string {
    try { return localStorage.getItem(AdminLocaleService.storageKey) ?? ''; } catch { return ''; }
  }

  set(locale: string): void {
    try { localStorage.setItem(AdminLocaleService.storageKey, locale); } catch { /* Storage is optional. */ }
  }
}
