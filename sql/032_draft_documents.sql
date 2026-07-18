-- "Uložiť rozpracované" vo formulárových nástrojoch — dokument sa uloží do
-- histórie bez generovania PDF, aby sa dal neskôr znova otvoriť a doplniť.
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).

ALTER TABLE formulare_generated_documents
  ADD COLUMN is_draft TINYINT(1) NOT NULL DEFAULT 0 AFTER form_data;
