import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';
import { ActivatedRoute, convertToParamMap } from '@angular/router';
import { ManagerPageComponent } from './manager-page.component';

describe('ManagerPageComponent', () => {
  it('loads a paginated manager list without using an iframe or exposing unprotected mutations', () => {
    TestBed.configureTestingModule({ imports: [ManagerPageComponent], providers: [provideHttpClient(), provideHttpClientTesting(), { provide: ActivatedRoute, useValue: { snapshot: { paramMap: convertToParamMap({ domain: 'CLIENT', api: 'Related' }) } } }] });
    const fixture = TestBed.createComponent(ManagerPageComponent); const requests = TestBed.inject(HttpTestingController);
    fixture.detectChanges();
    requests.expectOne('/admin/api/v2/managers/CLIENT/Related').flush({ ok: true, message: null, errors: {}, data: { domain: 'CLIENT', api: 'Related', endpoints: { list: '/CLIENT/api/Related', item: '/CLIENT/api/Related/{pk}' }, mutation: { supported: false }, query: { page: '__page', limit: '__limit', order: '__order', combo: '__combo' } } });
    requests.expectOne((request) => request.url === '/CLIENT/api/Related' && request.params.get('__limit') === '25').flush({ success: true, data: [{ IdRelated: 1, Title: 'Related fixture' }], total: 1, pages: 1 });
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('iframe')).toBeNull();
    expect(fixture.nativeElement.textContent).toContain('Related fixture');
    expect(fixture.nativeElement.textContent).toContain('modo consulta');
    requests.verify();
  });
});
