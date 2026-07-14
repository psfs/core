import { ChangeDetectionStrategy, Component, inject, OnInit, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NgFor, NgIf } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink, RouterLinkActive, RouterOutlet, Routes } from '@angular/router';
import { from, Observable } from 'rxjs';
import { AdminEnvelope } from './admin-contracts';
import { DocumentationPageComponent } from './documentation-page.component';
import { RoutesPageComponent } from './routes-page.component';
import { ConfigPageComponent } from './config-page.component';
import { UsersPageComponent } from './users-page.component';
import { ModulesPageComponent } from './modules-page.component';
import { ManagerPageComponent } from './manager-page.component';
import { AdminCsrfService } from './admin-csrf.service';
import { SwaggerExplorerComponent } from './swagger-explorer.component';
import { AdminLocaleService } from './admin-locale.service';
import { AdminTranslatePipe } from './admin-translate.pipe';
import { AdminTranslationService } from './admin-translation.service';

interface AdminMenuEntry {
  label: string;
  icon: string;
  path: string;
}

interface AdminMenuGroup {
  module: string;
  items: AdminMenuEntry[];
}

interface AdminBootstrap {
  identity: { username: string; role: string | null };
  locale?: string;
  locales?: string[];
  menu: AdminMenuGroup[];
  csrfToken?: string;
}

interface AdminNativePage {
  title: string;
  description: string;
  load?: () => Observable<unknown> | Promise<unknown>;
}

const FALLBACK_MENU: AdminMenuGroup[] = [
  {
    module: 'PSFS',
    items: [
      { label: 'Configuración general', icon: '⚙', path: '/config' },
      { label: 'Gestión de usuarios', icon: '👥', path: '/setup' },
      { label: 'Generar módulo', icon: '🧩', path: '/module' },
      { label: 'Rutas del sistema', icon: '🧭', path: '/routes' },
      { label: 'Documentación API', icon: '📚', path: '/api/docs' }
    ]
  }
];

@Component({
  selector: 'psfs-admin-root',
  imports: [AdminTranslatePipe, FormsModule, NgFor, NgIf, RouterLink, RouterLinkActive, RouterOutlet],
  template: `
    <div class="admin-app">
      <header class="admin-header">
        <a class="brand" routerLink="/" [attr.aria-label]="'PSFS Admin 2.0 home' | t">
          <span class="brand-mark" aria-hidden="true">P</span>
          <span>PSFS <strong>Admin</strong></span>
        </a>
        <div class="header-actions">
          <div class="identity" *ngIf="bootstrap() as state">
            <span class="identity-avatar" aria-hidden="true">{{ state.identity.username.slice(0, 1).toUpperCase() }}</span>
            <span><strong>{{ state.identity.username }}</strong><small>{{ (state.identity.role ?? 'Administration') | t }}</small></span>
          </div>
          <label class="locale-picker" *ngIf="bootstrap() as state"><span class="visually-hidden">{{ 'Language' | t }}</span><select [ngModel]="state.locale ?? ''" (ngModelChange)="switchLocale($event)"><option *ngFor="let locale of state.locales ?? []" [value]="locale">{{ locale }}</option></select></label>
          <button class="sidebar-toggle" type="button" (click)="toggleNavigation()" [attr.aria-expanded]="navigationOpen()" [attr.aria-label]="(navigationOpen() ? 'Close navigation' : 'Open navigation') | t" aria-controls="admin-navigation">
            <span class="visually-hidden">{{ (navigationOpen() ? 'Close navigation' : 'Open navigation') | t }}</span><span aria-hidden="true">☰</span>
          </button>
        </div>
      </header>
      <div class="admin-layout">
        <aside id="admin-navigation" class="admin-sidebar" [class.admin-sidebar--open]="navigationOpen()">
          <nav [attr.aria-label]="'Administration' | t">
            <section class="navigation-group" *ngFor="let group of menu()">
              <h2>{{ group.module }}</h2>
              <a *ngFor="let entry of group.items" [routerLink]="entry.path" routerLinkActive="navigation-link--active" (click)="closeNavigation()">
                <span class="navigation-icon" aria-hidden="true">{{ navigationSymbol(entry.icon) }}</span><span>{{ entry.label | t }}</span>
              </a>
            </section>
          </nav>
          <p class="sidebar-footer">PSFS · Angular 22</p>
        </aside>
        <main id="main-content" class="admin-main">
        <p class="load-error" *ngIf="loadError()">{{ loadError() }}</p>
        <router-outlet *ngIf="bootstrap()" />
        </main>
      </div>
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AdminShellComponent {
  private readonly http = inject(HttpClient);
  private readonly csrf = inject(AdminCsrfService);
  private readonly locale = inject(AdminLocaleService);
  private readonly translations = inject(AdminTranslationService);

  readonly bootstrap = signal<AdminBootstrap | null>(null);
  readonly menu = signal<AdminMenuGroup[]>(FALLBACK_MENU);
  readonly loadError = signal('');
  readonly navigationOpen = signal(false);

  constructor() {
    this.loadBootstrap();
  }

  private loadBootstrap(preferredLocale = ''): void {
    this.http.get<AdminBootstrap>('/admin/api/v2/bootstrap').subscribe({
      next: (state) => {
        this.csrf.set(state.csrfToken);
        const selectedLocale = preferredLocale || this.locale.current() || state.locale || '';
        if (selectedLocale) this.locale.set(selectedLocale);
        this.translations.setLocale(selectedLocale || state.locale || 'en_US');
        this.bootstrap.set({ ...state, locale: selectedLocale || state.locale });
        this.menu.set(state.menu.length ? state.menu : FALLBACK_MENU);
      },
      error: (error) => {
        console.error('Admin bootstrap request failed', error.status, error.message, error.url);
        this.loadError.set('El catálogo de administración no está disponible; se muestra la navegación base.');
      }
    });
  }

  toggleNavigation(): void {
    this.navigationOpen.update((open) => !open);
  }

  closeNavigation(): void {
    this.navigationOpen.set(false);
  }

  navigationSymbol(icon: string): string {
    const normalized = icon.toLowerCase();
    if (normalized.includes('cog')) return '⚙';
    if (normalized.includes('user')) return '◉';
    if (normalized.includes('layer')) return '◇';
    if (normalized.includes('folder') || normalized.includes('route')) return '⌘';
    if (normalized.includes('book') || normalized.includes('doc')) return '▤';
    return '•';
  }

  switchLocale(locale: string): void {
    if (!/^[a-z]{2}(?:_[A-Z]{2})?$/.test(locale)) return;
    this.locale.set(locale);
    this.translations.setLocale(locale);
    this.http.put<{ ok: boolean; data: { locale: string } }>(`/admin/api/v2/locale/${encodeURIComponent(locale)}`, {}).subscribe({
      next: () => this.loadBootstrap(locale),
      error: () => this.loadError.set('No se pudo cambiar el idioma.')
    });
  }
}

@Component({
  selector: 'psfs-admin-native-page',
  imports: [NgFor, NgIf],
  template: `
    <article class="native-page page" aria-live="polite">
      <header class="page-header">
        <div><p class="page-eyebrow">Administración</p><h1>{{ title() }}</h1></div>
      </header>
      <p *ngIf="state() === 'loading'" class="notice notice--info">Cargando contrato administrativo…</p>
      <ng-container *ngIf="state() === 'success'">
        <p class="page-description">{{ description() }}</p>
        <p class="notice notice--info">La vista nativa está preparada para consumir su contrato administrativo v2.</p>
      </ng-container>
      <section *ngIf="state() === 'error' && error() as failure" class="form-errors" aria-label="Errores de administración">
        <p>{{ failure.message ?? 'No se pudo cargar la pantalla administrativa.' }}</p>
        <ul *ngIf="errorMessages(failure).length">
          <li *ngFor="let message of errorMessages(failure)">{{ message }}</li>
        </ul>
      </section>
    </article>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AdminNativePageComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  readonly state = signal<'loading' | 'success' | 'error'>('loading');
  readonly error = signal<AdminEnvelope<null> | null>(null);

  ngOnInit(): void {
    const page = this.page();
    if (!page) {
      this.fail('Esta ruta no tiene un contrato de pantalla nativa disponible.');
      return;
    }

    if (!page.load) {
      this.state.set('success');
      return;
    }

    try {
      from(page.load()).subscribe({
        next: () => this.state.set('success'),
        error: (failure: unknown) => this.fail(this.errorMessage(failure))
      });
    } catch (failure: unknown) {
      this.fail(this.errorMessage(failure));
    }
  }

  title(): string {
    const domain = this.route.snapshot.paramMap.get('domain');
    const api = this.route.snapshot.paramMap.get('api');
    return this.page()?.title
      ?? (domain && api ? `Manager API: ${domain} / ${api}` : 'PSFS Admin 2.0');
  }

  description(): string {
    return this.page()?.description
      ?? 'Seleccione una sección del menú.';
  }

  errorMessages(failure: AdminEnvelope<null>): string[] {
    return Object.values(failure.errors).flat();
  }

  private page(): AdminNativePage | undefined {
    return this.route.snapshot.data['page'] as AdminNativePage | undefined;
  }

  private fail(message: string): void {
    this.error.set({ ok: false, message, data: null, errors: {} });
    this.state.set('error');
  }

  private errorMessage(failure: unknown): string {
    if (typeof failure === 'object' && failure !== null && 'message' in failure) {
      const message = (failure as { message?: unknown }).message;
      if (typeof message === 'string' && message.length > 0) {
        return message;
      }
    }

    return 'No se pudo cargar la pantalla administrativa.';
  }
}

export const ADMIN_ROUTES: Routes = [
  { path: '', component: AdminNativePageComponent, data: { page: { title: 'PSFS Admin 2.0', description: 'Launcher administrativo Angular 22.' } satisfies AdminNativePage } },
  { path: 'config', component: ConfigPageComponent },
  { path: 'setup', component: UsersPageComponent },
  { path: 'module', component: ModulesPageComponent },
  { path: 'routes', component: RoutesPageComponent },
  { path: 'api/docs', component: DocumentationPageComponent },
  { path: ':domain/swagger-ui', component: SwaggerExplorerComponent },
  { path: ':domain/:api', component: ManagerPageComponent },
  { path: ':domain/:api/:id', component: ManagerPageComponent },
  { path: '**', component: AdminNativePageComponent, data: { title: 'Ruta administrativa', description: 'La ruta se ha cargado mediante el fallback SPA.' } }
];
