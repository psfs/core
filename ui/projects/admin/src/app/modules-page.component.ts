import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { NgFor, NgIf } from '@angular/common';
import { AdminApiService } from './admin-api.service';
import { AdminEnvelope, AdminFormSchema } from './admin-contracts';
import { DynamicFormComponent, DynamicFormSubmission } from './dynamic-form.component';

@Component({
  selector: 'psfs-modules-page',
  imports: [DynamicFormComponent, NgFor, NgIf],
  template: `
    <article class="native-page page" aria-live="polite">
      <header class="page-header">
        <div>
          <p class="page-eyebrow">Desarrollo</p>
          <h1>Generador de módulos</h1>
          <p class="page-description">Crea la estructura base de un módulo PSFS desde un contrato nativo y validado.</p>
        </div>
      </header>
      <section class="notice notice--warning" aria-label="Advertencia de generación">
        Esta operación escribe archivos y genera migraciones. Revisa los datos antes de confirmar.
      </section>
      <p *ngIf="state() === 'loading'" class="notice notice--info">Cargando generador…</p>
      <section *ngIf="failure() as error" class="form-errors" aria-label="Errores del generador">
        <p>{{ error.message || 'No se pudo generar el módulo.' }}</p>
        <ul *ngIf="generalErrors().length"><li *ngFor="let message of generalErrors()">{{ message }}</li></ul>
      </section>
      <p *ngIf="message()" class="notice notice--success">{{ message() }}</p>
      <psfs-dynamic-form *ngIf="schema() as form" [schema]="form" [errors]="fieldErrors()" [disabled]="generating()" submitLabel="Generar módulo" (submitted)="create($event)" />
    </article>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class ModulesPageComponent {
  private readonly api = inject(AdminApiService);
  readonly schema = signal<AdminFormSchema | null>(null);
  readonly state = signal<'loading' | 'success' | 'error'>('loading');
  readonly generating = signal(false);
  readonly message = signal('');
  readonly failure = signal<AdminEnvelope<null> | null>(null);
  readonly fieldErrors = signal<Record<string, string[]>>({});

  constructor() {
    this.api.get<{ form: AdminFormSchema }>('modules/schema').subscribe({
      next: (response) => {
        this.schema.set(response.data.form);
        this.state.set('success');
      },
      error: (failure: unknown) => this.fail(failure, 'No se pudo cargar el generador.')
    });
  }

  create(submission: DynamicFormSubmission): void {
    this.generating.set(true);
    this.message.set('');
    this.failure.set(null);
    this.fieldErrors.set({});
    this.api.post<{ module: string }>('modules', submission).subscribe({
      next: (response) => {
        this.generating.set(false);
        this.message.set(response.message ?? `Módulo ${response.data.module} generado correctamente.`);
      },
      error: (failure: unknown) => {
        this.generating.set(false);
        this.fail(failure, 'No se pudo generar el módulo.');
      }
    });
  }

  generalErrors(): string[] {
    const failure = this.failure();
    if (!failure) return [];
    return Object.entries(failure.errors)
      .filter(([field]) => !(field in this.fieldErrors()))
      .flatMap(([, messages]) => messages);
  }

  private fail(failure: unknown, fallback: string): void {
    const envelope = this.envelope(failure, fallback);
    this.failure.set(envelope);
    this.fieldErrors.set(envelope.errors);
    if (this.schema() === null) {
      this.state.set('error');
    }
  }

  private envelope(failure: unknown, fallback: string): AdminEnvelope<null> {
    if (typeof failure === 'object' && failure !== null && 'ok' in failure && 'errors' in failure) {
      return failure as AdminEnvelope<null>;
    }
    return { ok: false, message: fallback, data: null, errors: {} };
  }
}
