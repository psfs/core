import { Injectable, signal } from '@angular/core';
import { Observable, ReplaySubject } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class AdminCsrfService {
  readonly token = signal('');
  private readonly issued = new ReplaySubject<string>(1);

  set(token: string | null | undefined): void {
    const value = token ?? '';
    this.token.set(value);
    if (value) this.issued.next(value);
  }

  /** Mutations wait for the authenticated bootstrap instead of sending an empty CSRF header. */
  waitForToken(): Observable<string> { return this.issued.asObservable(); }
}
