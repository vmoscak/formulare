-- Profesijné identifikačné čísla poradcu (SFA/VFA, registrácia v NBS) —
-- vypĺňa majiteľ portálu raz per poradca v admin.php, aby ich poradca
-- nemusel ručne prepisovať pri každej žiadosti "Zmena správcu zmluvy".
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).

ALTER TABLE formulare_advisors ADD COLUMN sfa_acquisition_no VARCHAR(40) NULL AFTER phone;
ALTER TABLE formulare_advisors ADD COLUMN sfa_personal_no VARCHAR(40) NULL AFTER sfa_acquisition_no;
ALTER TABLE formulare_advisors ADD COLUMN nbs_registration_no VARCHAR(40) NULL AFTER sfa_personal_no;
