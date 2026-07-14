import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { bootstrapApplication } from '@angular/platform-browser';
import { provideRouter } from '@angular/router';
import { ADMIN_ROUTES, AdminShellComponent } from './app/admin-shell.component';
import { adminCsrfInterceptor } from './app/admin-csrf.interceptor';
import { adminLocaleInterceptor } from './app/admin-locale.interceptor';

bootstrapApplication(AdminShellComponent, {
  providers: [provideHttpClient(withInterceptors([adminLocaleInterceptor, adminCsrfInterceptor])), provideRouter(ADMIN_ROUTES)]
}).catch((error: unknown) => console.error(error));
