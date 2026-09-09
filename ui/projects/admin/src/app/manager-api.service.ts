import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { AdminEnvelope } from './admin-contracts';

export interface ManagerMetadata {
  domain: string;
  api: string;
  endpoints: { list: string; item: string };
  mutation: { supported: false };
  query: { page: string; limit: string; order: string; combo: string };
}

export interface ManagerReadResponse<T> {
  data: T;
  success: boolean;
  total?: number;
  pages?: number;
  message?: string | null;
}

/**
 * Read-only compatibility boundary for generated manager APIs.
 * Components consume this service rather than constructing generated API URLs.
 */
@Injectable({ providedIn: 'root' })
export class ManagerApiService {
  constructor(private readonly http: HttpClient) {}

  metadata(domain: string, api: string): Observable<AdminEnvelope<ManagerMetadata>> {
    return this.http.get<AdminEnvelope<ManagerMetadata>>(`/admin/api/v2/managers/${encodeURIComponent(domain)}/${encodeURIComponent(api)}`);
  }

  list(metadata: ManagerMetadata, page: number, filter = ''): Observable<ManagerReadResponse<Record<string, unknown>[]>> {
    let params = new HttpParams().set(metadata.query.page, page).set(metadata.query.limit, 25);
    if (filter.trim()) params = params.set(metadata.query.combo, filter.trim());
    return this.http.get<ManagerReadResponse<Record<string, unknown>[]>>(metadata.endpoints.list, { params });
  }

  detail(metadata: ManagerMetadata, primaryKey: unknown): Observable<ManagerReadResponse<Record<string, unknown>>> {
    return this.http.get<ManagerReadResponse<Record<string, unknown>>>(metadata.endpoints.item.replace('{pk}', encodeURIComponent(String(primaryKey))));
  }
}
