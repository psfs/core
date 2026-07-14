import { Pipe, PipeTransform, inject } from '@angular/core';
import { AdminTranslationService } from './admin-translation.service';

@Pipe({ name: 't', standalone: true, pure: false })
export class AdminTranslatePipe implements PipeTransform {
  private readonly translations = inject(AdminTranslationService);

  transform(value: string): string {
    return this.translations.translate(value);
  }
}
