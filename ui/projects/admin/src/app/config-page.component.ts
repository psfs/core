import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { NgFor, NgIf } from '@angular/common';
import { AdminApiService } from './admin-api.service';
import { AdminEnvelope, AdminFormSchema } from './admin-contracts';
import { DynamicFormComponent, DynamicFormSubmission } from './dynamic-form.component';
import { AdminTranslatePipe } from './admin-translate.pipe';

@Component({
  selector: 'psfs-config-page',
  imports: [AdminTranslatePipe, DynamicFormComponent, NgFor, NgIf],
  template: `
    <article class="native-page page" aria-live="polite">
      <header class="page-header">
        <div>
          <p class="page-eyebrow">{{ 'Administration' | t }}</p>
          <h1>{{ 'General configuration' | t }}</h1>
          <p class="page-description">{{ 'Adjust PSFS parameters from a secure, validated form.' | t }}</p>
        </div>
      </header>
      <p *ngIf="state() === 'loading'" class="notice notice--info">{{ 'Loading configuration…' | t }}</p>
      <section *ngIf="failure() as error" class="form-errors" aria-label="Errores de configuración">
        <p>{{ error.message || 'No se pudo guardar la configuración.' }}</p>
        <ul *ngIf="generalErrors().length"><li *ngFor="let message of generalErrors()">{{ message }}</li></ul>
      </section>
      <p *ngIf="message()" class="notice notice--success">{{ message() }}</p>
      <psfs-dynamic-form *ngIf="schema() as form" [schema]="form" [errors]="fieldErrors()" [disabled]="saving()" (submitted)="save($event)" />
      <section class="config-extra" *ngIf="schema()">
        <div class="panel-heading"><div><h2>{{ 'Additional parameters' | t }}</h2><p>{{ 'Add keys not included in the base form.' | t }}</p></div><button class="button button--secondary" type="button" (click)="addExtra()">{{ 'Add parameter' | t }}</button></div>
        <datalist id="config-suggestions"><option *ngFor="let suggestion of suggestions()" [value]="suggestion"></option></datalist>
        <div class="config-extra-row" *ngFor="let extra of extras(); let index = index; trackBy: trackExtra">
          <input class="form-control" [value]="extra.key" list="config-suggestions" autocomplete="off" [placeholder]="'Free parameter name' | t" [attr.aria-label]="'Parameter name' | t" (input)="setExtra(index, $any($event.target).value, extra.value)">
          <input class="form-control" [value]="extra.value" [attr.aria-label]="'Parameter value' | t" (input)="setExtra(index, extra.key, $any($event.target).value)">
          <button class="button button--secondary" type="button" (click)="removeExtra(index)">{{ 'Remove' | t }}</button>
        </div>
      </section>
    </article>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class ConfigPageComponent {
  private readonly api = inject(AdminApiService);
  readonly schema = signal<AdminFormSchema | null>(null);
  readonly state = signal<'loading' | 'success' | 'error'>('loading');
  readonly saving = signal(false);
  readonly message = signal('');
  readonly failure = signal<AdminEnvelope<null> | null>(null);
  readonly fieldErrors = signal<Record<string, string[]>>({});
  readonly suggestions = signal<string[]>([]);
  readonly extras = signal<Array<{ key: string; value: string }>>([]);

  constructor() {
    this.api.get<{ form: AdminFormSchema; suggestions?: string[] }>('config').subscribe({
      next: (response) => {
        this.schema.set(response.data.form);
        this.suggestions.set(response.data.suggestions ?? []);
        this.state.set('success');
      },
      error: (failure: unknown) => this.fail(failure, 'No se pudo cargar la configuración.')
    });
  }

  save(submission: DynamicFormSubmission): void {
    this.saving.set(true);
    this.message.set('');
    this.failure.set(null);
    this.fieldErrors.set({});
    this.api.put<{ changed: string[] }>('config', { values: submission.values, extra: this.extraValues() }).subscribe({
      next: (response) => {
        this.saving.set(false);
        this.message.set(response.message ?? 'Configuración actualizada.');
      },
      error: (failure: unknown) => {
        this.saving.set(false);
        this.fail(failure, 'No se pudo guardar la configuración.');
      }
    });
  }

  generalErrors(): string[] {
    const failure = this.failure();
    if (!failure) {
      return [];
    }
    return Object.entries(failure.errors)
      .filter(([field]) => !(field in this.fieldErrors()))
      .flatMap(([, messages]) => messages);
  }

  addExtra(): void {
    this.extras.update((entries) => [...entries, { key: '', value: '' }]);
  }

  removeExtra(index: number): void {
    this.extras.update((entries) => entries.filter((_, entryIndex) => entryIndex !== index));
  }

  setExtra(index: number, key: string, value: string): void {
    this.extras.update((entries) => entries.map((entry, entryIndex) => entryIndex === index ? { key, value } : entry));
  }

  trackExtra(index: number): number { return index; }

  extraValues(): Record<string, string> {
    return Object.fromEntries(this.extras()
      .filter((entry) => entry.key.trim() !== '')
      .map((entry) => [entry.key.trim(), entry.value]));
  }

  private fail(failure: unknown, fallback: string): void {
    const envelope = this.envelope(failure, fallback);
    this.failure.set(envelope);
    this.fieldErrors.set(envelope.errors);
    this.state.set('error');
  }

  private envelope(failure: unknown, fallback: string): AdminEnvelope<null> {
    if (typeof failure === 'object' && failure !== null && 'ok' in failure && 'errors' in failure) {
      return failure as AdminEnvelope<null>;
    }
    return { ok: false, message: fallback, data: null, errors: {} };
  }
}
