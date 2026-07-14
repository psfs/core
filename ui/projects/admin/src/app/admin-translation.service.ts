import { Injectable, signal } from '@angular/core';

/**
 * Small, framework-owned catalogue for the v2 shell.  API payloads remain
 * untouched: labels returned by older PSFS installations are translated at
 * the presentation boundary too.
 */
const SPANISH: Record<string, string> = {
  'Administration': 'Administración',
  'Language': 'Idioma',
  'Open navigation': 'Abrir navegación',
  'Close navigation': 'Cerrar navegación',
  'Administrator': 'Administrador',
  'User manager': 'Gestión de usuarios',
  'PSFS user manager': 'Gestión de usuarios',
  'General configuration': 'Configuración general',
  'Generate new module': 'Generar módulo',
  'Module generator': 'Generador de módulos',
  'System routes viewer': 'Rutas del sistema',
  'System routes': 'Rutas del sistema',
  'API documentation': 'Documentación API',
  'Loading configuration…': 'Cargando configuración…',
  'Additional parameters': 'Parámetros adicionales',
  'Add keys not included in the base form.': 'Añade claves no incluidas en el formulario base.',
  'Add parameter': 'Añadir parámetro',
  'Free parameter name': 'Nombre libre del parámetro',
  'Parameter name': 'Nombre del parámetro',
  'Parameter value': 'Valor del parámetro',
  'Remove': 'Quitar',
  'Adjust PSFS parameters from a secure, validated form.': 'Ajusta los parámetros de PSFS desde un formulario seguro y validado.'
};

const ENGLISH = Object.fromEntries(Object.entries(SPANISH).map(([english, spanish]) => [spanish, english]));

@Injectable({ providedIn: 'root' })
export class AdminTranslationService {
  private readonly activeLocale = signal('en_US');

  setLocale(locale: string): void {
    this.activeLocale.set(locale);
  }

  translate(value: string): string {
    return this.activeLocale().startsWith('es')
      ? SPANISH[value] ?? value
      : ENGLISH[value] ?? value;
  }
}
