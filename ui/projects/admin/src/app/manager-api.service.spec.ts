import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { ManagerApiService } from './manager-api.service';

describe('ManagerApiService', () => {
  let api: ManagerApiService;
  let requests: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    api = TestBed.inject(ManagerApiService);
    requests = TestBed.inject(HttpTestingController);
  });

  afterEach(() => requests.verify());

  it('owns the read-only manager bridge and preserves its pagination contract', () => {
    api.list({ domain: 'CLIENT', api: 'Related', endpoints: { list: '/CLIENT/api/Related', item: '/CLIENT/api/Related/{pk}' }, mutation: { supported: false }, query: { page: '__page', limit: '__limit', order: '__order', combo: '__combo' } }, 2, 'fixture').subscribe();

    const request = requests.expectOne((candidate) => candidate.url === '/CLIENT/api/Related');
    expect(request.request.params.get('__page')).toBe('2');
    expect(request.request.params.get('__limit')).toBe('25');
    expect(request.request.params.get('__combo')).toBe('fixture');
    request.flush({ success: true, data: [], total: 0, pages: 1 });
  });
});
