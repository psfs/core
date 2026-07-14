import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { NgFor, NgIf } from '@angular/common';
import { RouterLink } from '@angular/router';
import { AdminApiService } from './admin-api.service';
import { AdminEnvelope } from './admin-contracts';

@Component({
  selector: 'psfs-documentation-page',
  imports: [NgFor, NgIf, RouterLink],
  template: `
    <article class="native-page page" aria-live="polite">
      <header class="page-header"><div><p class="page-eyebrow">Integración</p><h1>Documentación API</h1><p class="page-description">Explora los contratos OpenAPI publicados por cada dominio de PSFS.</p></div></header>
      <p *ngIf="state() === 'loading'" class="notice notice--info">Cargando dominios documentables…</p>
      <section *ngIf="error() as failure" class="form-errors" aria-label="Errores de documentación">
        <p>{{ failure.message ?? 'No se pudo cargar la documentación.' }}</p>
        <ul *ngIf="errorMessages(failure).length"><li *ngFor="let message of errorMessages(failure)">{{ message }}</li></ul>
      </section>
      <ng-container *ngIf="state() === 'success'">
        <p *ngIf="!domains().length" class="empty-state">No hay dominios API documentables.</p>
        <div *ngIf="domains().length" class="chip-list" aria-label="Dominios API">
          <button *ngFor="let domain of domains()" class="chip" [class.chip--active]="selectedDomain() === domain" type="button" (click)="selectDomain(domain)">{{ domain }}</button>
        </div>
        <section *ngIf="selectedDomain() as domain" class="documentation-choice">
          <p class="notice notice--info">Elige un explorador para {{ domain }}. No se incrusta una pantalla legacy.</p>
          <a class="button button--primary" [routerLink]="['/', domain, 'swagger-ui']" [queryParams]="{ document: documentPath(domain) }">Abrir explorador API</a>
        </section>
      </ng-container>
    </article>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class DocumentationPageComponent {
  private readonly api = inject(AdminApiService);
  readonly state = signal<'loading' | 'success' | 'error'>('loading');
  readonly domains = signal<string[]>([]);
  readonly documentPaths = signal<Record<string, string>>({});
  readonly selectedDomain = signal<string | null>(null);
  readonly error = signal<AdminEnvelope<null> | null>(null);

  constructor() {
    this.api.get<{ domains: string[]; documentPaths?: Record<string, string> }>('docs').subscribe({
      next: (response) => {
        this.domains.set(response.data.domains);
        this.documentPaths.set(response.data.documentPaths ?? {});
        this.state.set('success');
      },
      error: (failure: unknown) => this.fail(failure)
    });
  }

  documentPath(domain: string): string { return this.documentPaths()[domain] ?? `/${domain.toUpperCase()}/api/doc`; }

  selectDomain(domain: string): void {
    this.selectedDomain.set(domain);
  }

  errorMessages(failure: AdminEnvelope<null>): string[] {
    return Object.values(failure.errors).flat();
  }

  private fail(failure: unknown): void {
    if (typeof failure === 'object' && failure !== null && 'ok' in failure && 'errors' in failure) {
      this.error.set(failure as AdminEnvelope<null>);
    } else {
      this.error.set({ ok: false, message: 'No se pudo cargar la documentación.', data: null, errors: {} });
    }
    this.state.set('error');
  }
}
