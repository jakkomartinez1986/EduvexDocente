<?php

namespace App\Imports;

use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\User;
use App\Rules\EcuadorianPhone;
use App\Rules\FlexibleDni;
use App\Services\AcademicYearService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class StudentsImport implements ToCollection, WithHeadingRow
{
    protected bool $previewOnly;

    protected array $rows = [];

    protected int $totalRows = 0;

    protected int $validRows = 0;

    protected int $errorRows = 0;

    protected int $currentCodeNumber = 0;

    protected ?int $gradeId = null;

    protected ?int $yearId = null;

    public function __construct(bool $previewOnly = false, ?int $gradeId = null)
    {
        $this->previewOnly = $previewOnly;
        $this->gradeId = $gradeId;

        $lastStudent = Student::orderBy('id', 'desc')->first();
        $this->currentCodeNumber = $lastStudent ? $lastStudent->id : 0;

        if ($gradeId) {
            $this->yearId = app(AcademicYearService::class)->getActiveYearId();
        }
    }

    private function parseDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $value = trim($value);

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((int) $value))->format('Y-m-d');
        }

        $formats = ['j/n/Y', 'j/n/y', 'd/m/Y', 'd/m/y', 'j-m-Y', 'j-n-Y', 'd-m-Y', 'd-m-y', 'Y-m-d', 'm/d/Y', 'm-d-Y'];
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Exception $e) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function collection(Collection $rows): void
    {
        $this->totalRows = $rows->count();

        foreach ($rows as $row) {
            $rowData = [
                'name' => $row['nombres'] ?? $row['nombre'] ?? $row['name'] ?? null,
                'lastname' => $row['apellidos'] ?? $row['apellido'] ?? $row['lastname'] ?? null,
                'dni' => (string) ($row['dni'] ?? ''),
                'email' => $row['email'] ?? $row['correo'] ?? null,
                'phone' => (string) ($row['telefono'] ?? $row['phone'] ?? ''),
                'cellphone' => (string) ($row['celular'] ?? $row['cellphone'] ?? ''),
                'address' => $row['direccion'] ?? $row['address'] ?? null,
                'birth_date' => $this->parseDate($row['fecha_nacimiento'] ?? $row['FECHA_NACIMIENTO'] ?? $row['birth_date'] ?? null),
                'blood_type' => $row['tipo_sangre'] ?? $row['TIPO_SANGRE'] ?? $row['blood_type'] ?? null,
                'emergency_contact' => $row['contacto_emergencia'] ?? $row['CONTACTO_EMERGENCIA'] ?? $row['emergency_contact'] ?? null,
                'medical_info' => $row['info_medica'] ?? $row['INFO_MEDICA'] ?? $row['medical_info'] ?? null,
            ];

            try {
                $existingUser = $rowData['dni'] ? User::where('dni', $rowData['dni'])->first() : null;

                $rules = [
                    'name' => ['required', 'string', 'max:255'],
                    'lastname' => ['required', 'string', 'max:255'],
                    'dni' => ['required', new FlexibleDni],
                    'email' => ['nullable', 'email'],
                    'cellphone' => ['required', new EcuadorianPhone],
                ];

                if (! empty($rowData['phone'])) {
                    $rules['phone'] = [new EcuadorianPhone];
                }

                if (! $existingUser) {
                    $rules['dni'][] = 'unique:users,dni';
                    if (! empty($rowData['email'])) {
                        $rules['email'][] = 'unique:users,email';
                    }
                }

                $validator = Validator::make($rowData, $rules, [
                    'name.required' => 'Nombre es obligatorio',
                    'lastname.required' => 'Apellido es obligatorio',
                    'dni.required' => 'DNI es obligatorio',
                    'email.email' => 'Formato de email invalido',
                    'cellphone.required' => 'Celular es obligatorio',
                ]);

                if ($validator->fails()) {
                    $rowData['errors'] = $validator->errors()->first();
                    $this->errorRows++;
                } else {
                    $rowData['status'] = $existingUser ? 'actualizar' : 'nuevo';
                    $this->validRows++;
                }
            } catch (\Exception $e) {
                $rowData['errors'] = $e->getMessage();
                $this->errorRows++;
            }

            $this->rows[] = $rowData;

            if (! $this->previewOnly && empty($rowData['errors'])) {
                $this->currentCodeNumber++;
                $studentCode = 'EST-'.str_pad($this->currentCodeNumber, 9, '0', STR_PAD_LEFT);

                if ($existingUser) {
                    $existingUser->update([
                        'name' => $rowData['name'],
                        'lastname' => $rowData['lastname'],
                        'phone' => $rowData['phone'] ?: null,
                        'cellphone' => $rowData['cellphone'] ?: null,
                        'address' => $rowData['address'] ?: null,
                    ]);
                    $user = $existingUser;
                } else {
                    $provisionalEmail = $rowData['email'];
                    if (empty($provisionalEmail)) {
                        $firstName = strtolower(trim(explode(' ', $rowData['name'])[0]));
                        $lastName = strtolower(trim(explode(' ', $rowData['lastname'])[0]));
                        $provisionalEmail = $firstName.'.'.$lastName.'.'.mb_strtolower($studentCode).'@educaplusestudiante.edu.ec';
                    }
                    $user = User::create([
                        'name' => $rowData['name'],
                        'lastname' => $rowData['lastname'],
                        'dni' => $rowData['dni'],
                        'email' => $provisionalEmail,
                        'phone' => $rowData['phone'] ?: null,
                        'cellphone' => $rowData['cellphone'] ?: null,
                        'address' => $rowData['address'] ?: null,
                        'password' => $rowData['dni'].'passsword',
                        'status' => 1,
                        'must_change_password' => true,
                    ]);

                    $user->assignRole('ESTUDIANTE');
                }

                $existingStudent = Student::where('user_id', $user->id)->first();

                if ($existingStudent) {
                    $existingStudent->update([
                        'birth_date' => $rowData['birth_date'] ?: null,
                        'blood_type' => $rowData['blood_type'] ?? null,
                        'emergency_contact' => $rowData['emergency_contact'] ?? null,
                        'medical_info' => $rowData['medical_info'] ?? null,
                    ]);
                    $student = $existingStudent;
                } else {
                    $student = Student::create([
                        'user_id' => $user->id,
                        'student_code' => $studentCode,
                        'birth_date' => $rowData['birth_date'] ?: null,
                        'blood_type' => $rowData['blood_type'] ?? null,
                        'emergency_contact' => $rowData['emergency_contact'] ?? null,
                        'medical_info' => $rowData['medical_info'] ?? null,
                        'enrollment_date' => now()->toDateString(),
                    ]);
                }

                if ($this->gradeId && $this->yearId && $student) {
                    StudentEnrollment::updateOrCreate(
                        [
                            'student_id' => $student->id,
                            'grade_id' => $this->gradeId,
                            'year_id' => $this->yearId,
                        ],
                        [
                            'enrollment_date' => now()->toDateString(),
                            'status' => 'active',
                            'academic_year' => (string) now()->year,
                        ]
                    );
                }
            }
        }
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    public function getValidRows(): int
    {
        return $this->validRows;
    }

    public function getErrorRows(): int
    {
        return $this->errorRows;
    }
}
