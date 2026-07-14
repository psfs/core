import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { mergeMap, take } from 'rxjs';
import { AdminCsrfService } from './admin-csrf.service';

export const adminCsrfInterceptor: HttpInterceptorFn = (request, next) => {
  if (!['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method)) return next(request);
  const csrf = inject(AdminCsrfService);
  const token = csrf.token();
  if (token) return next(request.clone({ setHeaders: { 'X-PSFS-CSRF': token } }));

  return csrf.waitForToken().pipe(
    take(1),
    mergeMap((issued) => next(request.clone({ setHeaders: { 'X-PSFS-CSRF': issued } })))
  );
};
