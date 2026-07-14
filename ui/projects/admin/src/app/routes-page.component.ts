import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { NgFor, NgIf } from '@angular/common';
import { AdminApiService } from './admin-api.service';
import { AdminEnvelope } from './admin-contracts';

interface RouteRow {
  slug: string;
  route: unknown;
}

@Component({
  selector: 'psfs-routes-page',
  imports: [NgFor, NgIf],
  template: `
    <article class="native-page page" aria-live="polite">
      <header class="page-header">
        <div><p class="page-eyebrow">Sistema</p><h1>Rutas del sistema</h1><p class="page-description">Consulta el catálogo activo y regenera la definición de rutas cuando sea necesario.</p></div>
        <div class="page-actions" *ngIf="state() === 'success'"><button class="button button--primary" type="button" (click)="regenerate()" [disabled]="regenerating()">{{ regenerating() ? 'Regenerando…' : 'Regenerar rutas' }}</button></div>
      </header>
      <p *ngIf="state() === 'loading'" class="notice notice--info">Cargando catálogo de rutas…</p>
      <section *ngIf="error() as failure" class="form-errors" aria-label="Errores de rutas">
        <p>{{ failure.message ?? 'No se pudo cargar el catálogo.' }}</p>
        <ul *ngIf="errorMessages(failure).length"><li *ngFor="let message of errorMessages(failure)">{{ message }}</li></ul>
      </section>
      <ng-container *ngIf="state() === 'success'">
        <p *ngIf="operationMessage()" class="notice notice--success">{{ operationMessage() }}</p>
        <div class="table-container"><table class="data-table">
          <thead><tr><th><button class="table-sort" type="button" (click)="sortBy('slug')">Slug</button></th><th><button class="table-sort" type="button" (click)="sortBy('route')">Ruta</button></th></tr></thead>
          <tbody><tr *ngFor="let row of routes()"><td>{{ row.slug }}</td><td>{{ routeText(row.route) }}</td></tr></tbody>
        </table></div>
      </ng-container>
    </article>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class RoutesPageComponent {
  private readonly api = inject(AdminApiService);
  readonly state = signal<'loading' | 'success' | 'error'>('loading');
  readonly routes = signal<RouteRow[]>([]);
  readonly error = signal<AdminEnvelope<null> | null>(null);
  readonly regenerating = signal(false);
  readonly operationMessage = signal('');
  private sortColumn: keyof RouteRow = 'slug';
  private sortAscending = true;

  constructor() {
    this.api.get<{ routes: RouteRow[] }>('routes').subscribe({
      next: (response) => {
        this.routes.set(this.sorted(response.data.routes));
        this.state.set('success');
      },
      error: (failure: unknown) => this.fail(failure)
    });
  }

  sortBy(column: keyof RouteRow): void {
    this.sortAscending = this.sortColumn === column ? !this.sortAscending : true;
    this.sortColumn = column;
    this.routes.set(this.sorted(this.routes()));
  }

  regenerate(): void {
    this.regenerating.set(true);
    this.operationMessage.set('');
    this.api.post<{ regenerated: boolean }>('routes/regenerate', {}).subscribe({
      next: (response) => {
        this.operationMessage.set(response.message ?? 'Rutas regeneradas.');
        this.regenerating.set(false);
        this.loadRoutes();
      },
      error: (failure: unknown) => {
        this.regenerating.set(false);
        this.fail(failure);
      }
    });
  }

  routeText(route: unknown): string {
    return typeof route === 'string' ? route : JSON.stringify(route);
  }

  errorMessages(failure: AdminEnvelope<null>): string[] {
    return Object.values(failure.errors).flat();
  }

  private loadRoutes(): void {
    this.api.get<{ routes: RouteRow[] }>('routes').subscribe({
      next: (response) => this.routes.set(this.sorted(response.data.routes)),
      error: (failure: unknown) => this.fail(failure)
    });
  }

  private sorted(rows: RouteRow[]): RouteRow[] {
    const direction = this.sortAscending ? 1 : -1;
    return [...rows].sort((left, right) => direction * this.routeText(left[this.sortColumn]).localeCompare(this.routeText(right[this.sortColumn])));
  }

  private fail(failure: unknown): void {
    this.error.set(this.envelope(failure));
    this.state.set('error');
  }

  private envelope(failure: unknown): AdminEnvelope<null> {
    if (typeof failure === 'object' && failure !== null && 'ok' in failure && 'errors' in failure) {
      return failure as AdminEnvelope<null>;
    }
    return { ok: false, message: 'No se pudo cargar el catálogo de rutas.', data: null, errors: {} };
  }
}
