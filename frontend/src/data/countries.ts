/**
 * Countries shown in the customer address dropdown. ISO 3166-1 alpha-2
 * codes paired with localized names. Scope: EU member states plus
 * Hungary's neighbouring non-EU states — enough for a Hungarian B2B
 * CRM without bloating the list with countries that will never appear.
 *
 * Names are stored per locale; the address fieldset picks the user's
 * locale (falls back to English). Hungary is first by convention.
 */
export interface Country {
  code: string
  names: { hu: string; en: string; de: string; az: string; es: string; pl: string; pt: string; tr: string }
}

export const countries: Country[] = [
  { code: 'HU', names: { hu: 'Magyarország', en: 'Hungary', de: 'Ungarn', az: 'Macarıstan', es: 'Hungría', pl: 'Węgry', pt: 'Hungria', tr: 'Macaristan' } },
  { code: 'AT', names: { hu: 'Ausztria', en: 'Austria', de: 'Österreich', az: 'Avstriya', es: 'Austria', pl: 'Austria', pt: 'Áustria', tr: 'Avusturya' } },
  { code: 'BE', names: { hu: 'Belgium', en: 'Belgium', de: 'Belgien', az: 'Belçika', es: 'Bélgica', pl: 'Belgia', pt: 'Bélgica', tr: 'Belçika' } },
  { code: 'BG', names: { hu: 'Bulgária', en: 'Bulgaria', de: 'Bulgarien', az: 'Bolqarıstan', es: 'Bulgaria', pl: 'Bułgaria', pt: 'Bulgária', tr: 'Bulgaristan' } },
  { code: 'HR', names: { hu: 'Horvátország', en: 'Croatia', de: 'Kroatien', az: 'Xorvatiya', es: 'Croacia', pl: 'Chorwacja', pt: 'Croácia', tr: 'Hırvatistan' } },
  { code: 'CY', names: { hu: 'Ciprus', en: 'Cyprus', de: 'Zypern', az: 'Kipr', es: 'Chipre', pl: 'Cypr', pt: 'Chipre', tr: 'Kıbrıs' } },
  { code: 'CZ', names: { hu: 'Csehország', en: 'Czechia', de: 'Tschechien', az: 'Çexiya', es: 'Chequia', pl: 'Czechy', pt: 'Chéquia', tr: 'Çekya' } },
  { code: 'DK', names: { hu: 'Dánia', en: 'Denmark', de: 'Dänemark', az: 'Danimarka', es: 'Dinamarca', pl: 'Dania', pt: 'Dinamarca', tr: 'Danimarka' } },
  { code: 'EE', names: { hu: 'Észtország', en: 'Estonia', de: 'Estland', az: 'Estoniya', es: 'Estonia', pl: 'Estonia', pt: 'Estónia', tr: 'Estonya' } },
  { code: 'FI', names: { hu: 'Finnország', en: 'Finland', de: 'Finnland', az: 'Finlandiya', es: 'Finlandia', pl: 'Finlandia', pt: 'Finlândia', tr: 'Finlandiya' } },
  { code: 'FR', names: { hu: 'Franciaország', en: 'France', de: 'Frankreich', az: 'Fransa', es: 'Francia', pl: 'Francja', pt: 'França', tr: 'Fransa' } },
  { code: 'DE', names: { hu: 'Németország', en: 'Germany', de: 'Deutschland', az: 'Almaniya', es: 'Alemania', pl: 'Niemcy', pt: 'Alemanha', tr: 'Almanya' } },
  { code: 'GR', names: { hu: 'Görögország', en: 'Greece', de: 'Griechenland', az: 'Yunanıstan', es: 'Grecia', pl: 'Grecja', pt: 'Grécia', tr: 'Yunanistan' } },
  { code: 'IE', names: { hu: 'Írország', en: 'Ireland', de: 'Irland', az: 'İrlandiya', es: 'Irlanda', pl: 'Irlandia', pt: 'Irlanda', tr: 'İrlanda' } },
  { code: 'IT', names: { hu: 'Olaszország', en: 'Italy', de: 'Italien', az: 'İtaliya', es: 'Italia', pl: 'Włochy', pt: 'Itália', tr: 'İtalya' } },
  { code: 'LV', names: { hu: 'Lettország', en: 'Latvia', de: 'Lettland', az: 'Latviya', es: 'Letonia', pl: 'Łotwa', pt: 'Letónia', tr: 'Letonya' } },
  { code: 'LT', names: { hu: 'Litvánia', en: 'Lithuania', de: 'Litauen', az: 'Litva', es: 'Lituania', pl: 'Litwa', pt: 'Lituânia', tr: 'Litvanya' } },
  { code: 'LU', names: { hu: 'Luxemburg', en: 'Luxembourg', de: 'Luxemburg', az: 'Lüksemburq', es: 'Luxemburgo', pl: 'Luksemburg', pt: 'Luxemburgo', tr: 'Lüksemburg' } },
  { code: 'MT', names: { hu: 'Málta', en: 'Malta', de: 'Malta', az: 'Malta', es: 'Malta', pl: 'Malta', pt: 'Malta', tr: 'Malta' } },
  { code: 'NL', names: { hu: 'Hollandia', en: 'Netherlands', de: 'Niederlande', az: 'Niderland', es: 'Países Bajos', pl: 'Holandia', pt: 'Países Baixos', tr: 'Hollanda' } },
  { code: 'PL', names: { hu: 'Lengyelország', en: 'Poland', de: 'Polen', az: 'Polşa', es: 'Polonia', pl: 'Polska', pt: 'Polónia', tr: 'Polonya' } },
  { code: 'PT', names: { hu: 'Portugália', en: 'Portugal', de: 'Portugal', az: 'Portuqaliya', es: 'Portugal', pl: 'Portugalia', pt: 'Portugal', tr: 'Portekiz' } },
  { code: 'RO', names: { hu: 'Románia', en: 'Romania', de: 'Rumänien', az: 'Rumıniya', es: 'Rumanía', pl: 'Rumunia', pt: 'Roménia', tr: 'Romanya' } },
  { code: 'SK', names: { hu: 'Szlovákia', en: 'Slovakia', de: 'Slowakei', az: 'Slovakiya', es: 'Eslovaquia', pl: 'Słowacja', pt: 'Eslováquia', tr: 'Slovakya' } },
  { code: 'SI', names: { hu: 'Szlovénia', en: 'Slovenia', de: 'Slowenien', az: 'Sloveniya', es: 'Eslovenia', pl: 'Słowenia', pt: 'Eslovénia', tr: 'Slovenya' } },
  { code: 'ES', names: { hu: 'Spanyolország', en: 'Spain', de: 'Spanien', az: 'İspaniya', es: 'España', pl: 'Hiszpania', pt: 'Espanha', tr: 'İspanya' } },
  { code: 'SE', names: { hu: 'Svédország', en: 'Sweden', de: 'Schweden', az: 'İsveç', es: 'Suecia', pl: 'Szwecja', pt: 'Suécia', tr: 'İsveç' } },
  // Non-EU neighbours of Hungary plus other commonly-needed European states.
  { code: 'RS', names: { hu: 'Szerbia', en: 'Serbia', de: 'Serbien', az: 'Serbiya', es: 'Serbia', pl: 'Serbia', pt: 'Sérvia', tr: 'Sırbistan' } },
  { code: 'UA', names: { hu: 'Ukrajna', en: 'Ukraine', de: 'Ukraine', az: 'Ukrayna', es: 'Ucrania', pl: 'Ukraina', pt: 'Ucrânia', tr: 'Ukrayna' } },
  { code: 'GB', names: { hu: 'Egyesült Királyság', en: 'United Kingdom', de: 'Vereinigtes Königreich', az: 'Birləşmiş Krallıq', es: 'Reino Unido', pl: 'Wielka Brytania', pt: 'Reino Unido', tr: 'Birleşik Krallık' } },
  { code: 'CH', names: { hu: 'Svájc', en: 'Switzerland', de: 'Schweiz', az: 'İsveçrə', es: 'Suiza', pl: 'Szwajcaria', pt: 'Suíça', tr: 'İsviçre' } },
  { code: 'NO', names: { hu: 'Norvégia', en: 'Norway', de: 'Norwegen', az: 'Norveç', es: 'Noruega', pl: 'Norwegia', pt: 'Noruega', tr: 'Norveç' } },
  { code: 'TR', names: { hu: 'Törökország', en: 'Turkey', de: 'Türkei', az: 'Türkiyə', es: 'Turquía', pl: 'Turcja', pt: 'Turquia', tr: 'Türkiye' } },
]

export const countryByCode = (code: string | null): Country | undefined =>
  null === code ? undefined : countries.find((c) => c.code === code)
