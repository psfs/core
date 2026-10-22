import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { NgFor, NgIf } from '@angular/common';
import { AdminApiService } from './admin-api.service';
import { AdminEnvelope, AdminFormSchema } from './admin-contracts';
import { DynamicFormComponent, DynamicFormSubmission } from './dynamic-form.component';
import { ConfirmDialogComponent } from './confirm-dialog.component';
import { AdminTranslatePipe } from './admin-translate.pipe';
import { AdminTranslationService } from './admin-translation.service';

interface AdminUser {
  username: string;
  role: string;
  class: string;
  profile: string;
}

interface UsersContract {
  users: AdminUser[];
  form: AdminFormSchema;
  profiles: Record<string, string>;
}

@Component({
  selector: 'psfs-users-page',
  imports: [AdminTranslatePipe, DynamicFormComponent, ConfirmDialogComponent, NgFor, NgIf],
  template: `
    <article class="native-page page users-page" aria-live="polite">
      <header class="page-header">
        <div>
          <p class="page-eyebrow">{{ 'Administración' | t }}</p>
          <h1>{{ 'Gestión de usuarios' | t }}</h1>
          <p class="page-description">{{ 'Gestiona las credenciales administrativas de PSFS sin abandonar la aplicación.' | t }}</p>
        </div>
      </header>

      <p *ngIf="state() === 'loading'" class="notice notice--info">{{ 'Cargando usuarios…' | t }}</p>
      <section *ngIf="failure() as error" class="form-errors" [attr.aria-label]="'Errores de usuarios' | t">
        <p>{{ (error.message || 'No se pudo completar la operación.') | t }}</p>
        <ul *ngIf="generalErrors().length"><li *ngFor="let message of generalErrors()">{{ message }}</li></ul>
      </section>
      <psfs-confirm-dialog [open]="pendingDelete() !== ''" title="Eliminar usuario" [message]="deleteMessage()" confirmLabel="Eliminar" (cancel)="pendingDelete.set('')" (confirm)="confirmRemove()" />
      <p *ngIf="message()" class="notice notice--success">{{ message() | t }}</p>

      <section *ngIf="state() === 'success'" class="users-layout">
        <div class="panel users-list">
          <div class="panel-heading users-list__heading"><div><h2>{{ 'Usuarios activos' | t }}</h2><p>{{ users().length }} {{ 'configurados' | t }}</p></div><label class="users-filter"><span class="visually-hidden">{{ 'Filtrar usuarios' | t }}</span><input class="form-control" type="search" [placeholder]="'Buscar usuario o rol' | t" [value]="filter()" (input)="setFilter($any($event.target).value)"></label></div>
          <div class="table-container" *ngIf="filteredUsers().length; else emptyUsers">
            <table class="data-table">
              <thead><tr><th scope="col">{{ 'Alias' | t }}</th><th scope="col">{{ 'Rol' | t }}</th><th scope="col"><span class="visually-hidden">{{ 'Acciones' | t }}</span></th></tr></thead>
              <tbody>
                <tr *ngFor="let user of visibleUsers()">
                  <td><strong>{{ user.username }}</strong></td>
                  <td><span class="role-badge" [class]="'role-badge role-badge--' + user.class">{{ user.role | t }}</span></td>
                  <td class="table-actions"><button type="button" class="button button--secondary button--small" [disabled]="saving()" (click)="edit(user)">{{ 'Editar' | t }}</button><button type="button" class="button button--danger button--small" [disabled]="saving()" (click)="remove(user.username)">{{ 'Eliminar' | t }}</button></td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="pagination" *ngIf="pageCount() > 1" [attr.aria-label]="'Paginación de usuarios' | t">
            <button class="button button--secondary button--small" type="button" [disabled]="page() === 0" (click)="previousPage()">{{ 'Anterior' | t }}</button>
            <span>{{ 'Página' | t }} {{ page() + 1 }} {{ 'de' | t }} {{ pageCount() }}</span>
            <button class="button button--secondary button--small" type="button" [disabled]="page() + 1 >= pageCount()" (click)="nextPage()">{{ 'Siguiente' | t }}</button>
          </div>
          <ng-template #emptyUsers><p class="empty-state">{{ 'No hay usuarios configurados todavía.' | t }}</p></ng-template>
        </div>

        <section class="panel users-create" *ngIf="activeSchema() as form">
          <div class="panel-heading"><div><h2>{{ (editing() ? 'Editar usuario' : 'Nuevo usuario') | t }}</h2><p>{{ (editing() ? 'La contraseña se sustituirá al guardar.' : 'Define alias, contraseña y rol.') | t }}</p></div><button *ngIf="editing()" class="button button--secondary" type="button" (click)="cancelEdit()">{{ 'Cancelar' | t }}</button></div>
          <psfs-dynamic-form [schema]="form" [errors]="fieldErrors()" [disabled]="saving()" [submitLabel]="(editing() ? 'Actualizar usuario' : 'Crear usuario') | t" (submitted)="save($event)" />
        </section>
      </section>
    </article>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class UsersPageComponent {
  private readonly api = inject(AdminApiService);
  private readonly translations = inject(AdminTranslationService);
  readonly users = signal<AdminUser[]>([]);
  readonly schema = signal<AdminFormSchema | null>(null);
  readonly profiles = signal<Record<string, string>>({});
  readonly state = signal<'loading' | 'success' | 'error'>('loading');
  readonly saving = signal(false);
  readonly message = signal('');
  readonly failure = signal<AdminEnvelope<null> | null>(null);
  readonly fieldErrors = signal<Record<string, string[]>>({});
  readonly pendingDelete = signal('');
  readonly deleteMessage = computed(() => this.translations.translate('User {{username}} will be deleted. This action cannot be undone.', { username: this.pendingDelete() }));
  readonly editing = signal<AdminUser | null>(null);
  readonly activeSchema = computed<AdminFormSchema | null>(() => {
    const form = this.schema();
    const user = this.editing();
    if (!form || !user) return form;
    return {
      ...form,
      fields: {
        ...form.fields,
        username: { ...form.fields['username'], value: user.username },
        password: { ...form.fields['password'], value: '' },
        profile: { ...form.fields['profile'], value: user.profile },
      }
    };
  });
  readonly filter = signal('');
  readonly page = signal(0);
  readonly filteredUsers = computed(() => {
    const query = this.filter().trim().toLocaleLowerCase();
    if (!query) return this.users();
    return this.users().filter((user) => `${user.username} ${user.role}`.toLocaleLowerCase().includes(query));
  });
  readonly pageCount = computed(() => Math.max(1, Math.ceil(this.filteredUsers().length / 20)));
  readonly visibleUsers = computed(() => {
    const start = this.page() * 20;
    return this.filteredUsers().slice(start, start + 20);
  });

  constructor() {
    this.load();
  }

  save(submission: DynamicFormSubmission): void {
    this.saving.set(true);
    this.resetFeedback();
    const current = this.editing();
    const request = current
      ? this.api.put<Record<string, never>>(`users/${encodeURIComponent(current.username)}`, submission)
      : this.api.post<Record<string, never>>('users', submission);
    request.subscribe({
      next: (response) => {
        this.saving.set(false);
        this.message.set(response.message ?? (current ? 'Usuario actualizado correctamente.' : 'Usuario creado correctamente.'));
        this.editing.set(null);
        this.load();
      },
      error: (failure: unknown) => {
        this.saving.set(false);
        this.fail(failure, 'No se pudo crear el usuario.');
      }
    });
  }

  remove(username: string): void {
    this.pendingDelete.set(username);
  }

  edit(user: AdminUser): void {
    this.editing.set(user);
    this.resetFeedback();
  }

  cancelEdit(): void {
    this.editing.set(null);
    this.resetFeedback();
  }

  setFilter(value: string): void {
    this.filter.set(value);
    this.page.set(0);
  }

  previousPage(): void { this.page.update((current) => Math.max(0, current - 1)); }

  nextPage(): void { this.page.update((current) => Math.min(this.pageCount() - 1, current + 1)); }

  confirmRemove(): void {
    const username = this.pendingDelete();
    if (!username) return;
    this.pendingDelete.set('');

    this.saving.set(true);
    this.resetFeedback();
    this.api.delete<Record<string, never>>('users', { user: username }).subscribe({
      next: (response) => {
        this.saving.set(false);
        this.message.set(response.message ?? 'Usuario eliminado correctamente.');
        this.load();
      },
      error: (failure: unknown) => {
        this.saving.set(false);
        this.fail(failure, 'No se pudo eliminar el usuario.');
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

  private load(): void {
    this.api.get<UsersContract>('users').subscribe({
      next: (response) => {
        this.users.set(response.data.users);
        if (this.page() >= this.pageCount()) this.page.set(Math.max(0, this.pageCount() - 1));
        this.schema.set(response.data.form);
        this.profiles.set(response.data.profiles);
        this.state.set('success');
      },
      error: (failure: unknown) => this.fail(failure, 'No se pudo cargar la gestión de usuarios.')
    });
  }

  private resetFeedback(): void {
    this.message.set('');
    this.failure.set(null);
    this.fieldErrors.set({});
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
