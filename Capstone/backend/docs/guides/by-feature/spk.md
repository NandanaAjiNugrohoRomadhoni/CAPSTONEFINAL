# SPK (Surat Perintah Kerja) Feature Guide

Automates stock recommendations based on meal schedules and patient counts.

## Endpoints

### SPK Basah (Fresh Items)
- `POST /api/v1/spk/basah/generate`: Generate recommendations.
- `GET /api/v1/spk/basah/history`: List previous calculations.
- `POST /api/v1/spk/basah/history/{id}/override`: Adjust recommended quantities.
- `POST /api/v1/spk/basah/history/{id}/post-stock`: Commit recommendations to stock (Admin).

### SPK Kering & Pengemas (Dry/Packaging Items)
- `POST /api/v1/spk/kering-pengemas/generate`: Generate recommendations.
- `GET /api/v1/spk/kering-pengemas/history`: List previous calculations.
- `POST /api/v1/spk/kering-pengemas/history/{id}/post-stock`: Commit recommendations to stock (Admin).

## Business Rules

- **Calculation Logic**: Uses Dish Compositions × Estimated Patients to determine required quantities.
- **Overrides**: Users can manually adjust the system-calculated recommendation before posting.
- **Posting**: Once posted, the SPK creates an `OUT` transaction. A posted SPK cannot be edited.

## Related Documentation
- [SPK Basah Workflow](../by-workflow/spk-basah-workflow.md)
- [Menu Planning Guide](./menu-planning.md)
