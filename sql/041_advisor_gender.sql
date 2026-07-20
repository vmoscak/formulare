-- Pohlavie poradcu ('m'/'z') — na správny gramatický rod v textoch, kde
-- nástroj hovorí o samotnom poradcovi v 1. osobe (napr. "chcel/-a by som",
-- "mohol/-la by som pomôcť" v Generátore recenzií, Kartičke "Odporučte ma"
-- a Warm-Intro WhatsApp). Nastavuje sa raz v Admine, rovnako ako farba.
ALTER TABLE formulare_advisors ADD COLUMN gender CHAR(1) NOT NULL DEFAULT 'm';
