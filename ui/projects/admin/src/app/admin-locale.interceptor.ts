import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { AdminLocaleService } from './admin-locale.service';

export const adminLocaleInterceptor: HttpInterceptorFn = (request, next) => {
  if (!request.url.startsWith('/admin/api/v2/')) return next(request);
  const locale = inject(AdminLocaleService).current();
  return locale ? next(request.clone({ setHeaders: { 'X-API-LANG': locale } })) : next(request);
};
