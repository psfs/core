import { HttpClient, type HttpErrorResponse } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable, catchError, throwError } from 'rxjs';
import { AdminEnvelope } from './admin-contracts';

@Injectable({ providedIn: 'root' })
export class AdminApiService {
  private static readonly root = '/admin/api/v2/';

  constructor(private readonly http: HttpClient) {}

  get<T>(path: string): Observable<AdminEnvelope<T>> {
    return this.request(() => this.http.get<AdminEnvelope<T>>(this.url(path)));
  }

  put<T>(path: string, payload: unknown): Observable<AdminEnvelope<T>> {
    return this.request(() => this.http.put<AdminEnvelope<T>>(this.url(path), payload));
  }

  post<T>(path: string, payload: unknown): Observable<AdminEnvelope<T>> {
    return this.request(() => this.http.post<AdminEnvelope<T>>(this.url(path), payload));
  }

  delete<T>(path: string, payload?: unknown): Observable<AdminEnvelope<T>> {
    return this.request(() => this.http.delete<AdminEnvelope<T>>(this.url(path), { body: payload }));
  }

  private url(path: string): string {
    return path.startsWith(AdminApiService.root)
      ? path
      : `${AdminApiService.root}${path.replace(/^\/+/, '')}`;
  }

  private request<T>(operation: () => Observable<AdminEnvelope<T>>): Observable<AdminEnvelope<T>> {
    return operation().pipe(
      catchError((error: unknown) => {
        if (this.isHttpErrorResponse(error) && this.isEnvelope(error.error)) {
          return throwError(() => error.error);
        }

        return throwError(() => error);
      })
    );
  }

  private isEnvelope(value: unknown): value is AdminEnvelope<null> {
    if (typeof value !== 'object' || value === null) {
      return false;
    }

    const envelope = value as Partial<AdminEnvelope<null>>;
    return typeof envelope.ok === 'boolean'
      && (typeof envelope.message === 'string' || envelope.message === null)
      && typeof envelope.errors === 'object'
      && envelope.errors !== null;
  }

  private isHttpErrorResponse(error: unknown): error is HttpErrorResponse {
    return typeof error === 'object'
      && error !== null
      && 'error' in error
      && 'status' in error
      && typeof (error as { status: unknown }).status === 'number';
  }
}
