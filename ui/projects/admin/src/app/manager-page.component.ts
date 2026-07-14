import { ChangeDetectionStrategy, Component, OnInit, computed, inject, signal } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { NgFor, NgIf, JsonPipe } from '@angular/common';
import { ActivatedRoute } from '@angular/router';

interface ManagerMetadata {
  domain: string;
  api: string;
  endpoints: { list: string; item: string };
  mutation: { supported: boolean };
  query: { page: string; limit: string; order: string; combo: string };
}

interface LegacyResponse<T> {
  data: T;
  success: boolean;
  total?: number;
  pages?: number;
  message?: string | null;
}

@Component({
  selector: 'psfs-manager-page',
  imports: [NgFor, NgIf, JsonPipe],
  template: `
    <article class="page manager-page" aria-live="polite">
      <header class="page-header">
        <div><p class="page-eyebrow">Manager API</p><h1>{{ title() }}</h1></div>
      </header>

      <p class="notice notice--info" *ngIf="loading()">Cargando datos del manager…</p>
      <section class="form-errors" *ngIf="error() as failure"><p>{{ failure }}</p></section>

      <section class="card manager-toolbar" *ngIf="metadata()">
        <label class="form-field"><span>Filtrar registros</span><input class="form-control" #filterInput [value]="filter()" (keyup.enter)="applyFilter(filterInput.value)" placeholder="Busca por texto o campo"></label>
        <button class="button button--secondary" type="button" (click)="applyFilter(filterInput.value)">Aplicar filtro</button>
        <span class="table-meta" *ngIf="total() !== null">{{ total() }} registros</span>
      </section>

      <p class="notice notice--info" *ngIf="metadata() && !metadata()!.mutation.supported">Este manager se muestra en modo consulta. Las altas, cambios y borrados se habilitarán únicamente mediante un endpoint v2 que aplique CSRF y autorización en el servidor.</p>

      <section class="card table-card" *ngIf="rows().length || !loading()">
        <div class="table-scroll">
          <table>
            <thead><tr><th *ngFor="let column of columns()">{{ column }}</th><th>Acciones</th></tr></thead>
            <tbody>
              <tr *ngFor="let row of rows()">
                <td *ngFor="let column of columns()">{{ display(row[column]) }}</td>
                <td><button class="button button--text" type="button" (click)="openDetail(row)">Ver detalle</button></td>
              </tr>
              <tr *ngIf="!rows().length"><td [attr.colspan]="columns().length + 1">No hay registros para la consulta actual.</td></tr>
            </tbody>
          </table>
        </div>
        <footer class="pagination" *ngIf="pages() > 1"><button class="button button--secondary" type="button" (click)="previous()" [disabled]="page() <= 1">Anterior</button><span>Página {{ page() }} de {{ pages() }}</span><button class="button button--secondary" type="button" (click)="next()" [disabled]="page() >= pages()">Siguiente</button></footer>
      </section>

      <section class="card" *ngIf="detail() as item"><header class="card-header"><h2>Detalle</h2><button class="button button--text" type="button" (click)="detail.set(null)">Cerrar</button></header><pre class="json-view">{{ item | json }}</pre></section>
    </article>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class ManagerPageComponent implements OnInit {
  private readonly http = inject(HttpClient);
  private readonly route = inject(ActivatedRoute);
  readonly metadata = signal<ManagerMetadata | null>(null);
  readonly rows = signal<Record<string, unknown>[]>([]);
  readonly detail = signal<Record<string, unknown> | null>(null);
  readonly loading = signal(true);
  readonly error = signal('');
  readonly filter = signal('');
  readonly page = signal(1);
  readonly pages = signal(1);
  readonly total = signal<number | null>(null);
  readonly columns = computed(() => Object.keys(this.rows()[0] ?? {}));
  readonly title = computed(() => {
    const metadata = this.metadata();
    return metadata ? `${metadata.domain} / ${metadata.api}` : 'Manager API';
  });

  ngOnInit(): void {
    const domain = this.route.snapshot.paramMap.get('domain');
    const api = this.route.snapshot.paramMap.get('api');
    if (!domain || !api) { this.error.set('Faltan los identificadores del manager.'); this.loading.set(false); return; }
    this.http.get<{ ok: boolean; data: ManagerMetadata; message: string | null; errors: Record<string, string[]> }>(`/admin/api/v2/managers/${encodeURIComponent(domain)}/${encodeURIComponent(api)}`).subscribe({
      next: (response) => {
        if (!response.ok) { this.error.set(response.message ?? 'No se pudo abrir el manager.'); this.loading.set(false); return; }
        this.metadata.set(response.data);
        this.loadList();
      },
      error: (failure) => { this.error.set(failure?.error?.message ?? 'No se pudo abrir el manager.'); this.loading.set(false); }
    });
  }

  applyFilter(value: string): void { this.filter.set(value); this.page.set(1); this.loadList(); }
  previous(): void { if (this.page() > 1) { this.page.update((value) => value - 1); this.loadList(); } }
  next(): void { if (this.page() < this.pages()) { this.page.update((value) => value + 1); this.loadList(); } }
  display(value: unknown): string { return typeof value === 'object' ? JSON.stringify(value) : String(value ?? ''); }

  openDetail(row: Record<string, unknown>): void {
    const metadata = this.metadata();
    const primaryKey = this.primaryKey(row);
    if (!metadata || primaryKey === null) { this.detail.set(row); return; }
    this.http.get<LegacyResponse<Record<string, unknown>>>(metadata.endpoints.item.replace('{pk}', encodeURIComponent(String(primaryKey)))).subscribe({
      next: (response) => this.detail.set(response.data ?? row),
      error: () => this.detail.set(row)
    });
  }

  private loadList(): void {
    const metadata = this.metadata(); if (!metadata) return;
    this.loading.set(true); this.error.set('');
    let params = new HttpParams().set(metadata.query.page, this.page()).set(metadata.query.limit, 25);
    if (this.filter().trim()) params = params.set(metadata.query.combo, this.filter().trim());
    this.http.get<LegacyResponse<Record<string, unknown>[]>>(metadata.endpoints.list, { params }).subscribe({
      next: (response) => { this.rows.set(Array.isArray(response.data) ? response.data : []); this.total.set(response.total ?? this.rows().length); this.pages.set(response.pages ?? 1); this.loading.set(false); if (!response.success && response.message) this.error.set(response.message); },
      error: (failure) => { this.rows.set([]); this.loading.set(false); this.error.set(failure?.error?.message ?? 'La API del manager no está disponible.'); }
    });
  }

  private primaryKey(row: Record<string, unknown>): unknown { return row['__pk'] ?? row['id'] ?? row['Id'] ?? Object.values(row)[0] ?? null; }
}
