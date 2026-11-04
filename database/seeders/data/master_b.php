<?php
// Exact values copied from live abmmsampark.org (sub-admin view). '|' separated.
$times = [];
for ($m = 4 * 60; $m <= 22 * 60 + 30; $m += 10) { $times[] = date('h:i A', mktime(intdiv($m, 60), $m % 60)); }  // 04:00 AM ... 10:30 PM (10-min steps, as in Send Notification form)

return [
    'relation' => 'AUNTY|BROTHER|BROTHER-IN-LAW|CHACHA (KAKAJI)|CHACHI (KAKIJI)|DAUGHTER|DAUGHTER OF GRANDSON|DAUGHTER-IN-LAW (BAHU)|FATHER|FATHER-IN-LAW (SASUR)|GRAND DAUGHTER (POTI)|GRAND DAUGHTER-IN-LAW|GRAND MOTHER|GRAND SON (POTA)|GRANDFATHER|HUSBAND|MOTHER|MOTHER-IN-LAW|NEPHEW (BHATIJA)|NEPHEWS WIFE|NIECE (BHATIJI)|OTHER|SISTER|SISTER-IN-LAW|SON|SON OF GRANDSON|SPOUSE|STEP MOTHER|UNCLE|WIFE',
    'tehsil' => 'AAMER|AMBER|BAGRU|BANIPARK|BASSI|CHAKSU|CHOMU|DUDU|DUDU (HQ. MAUZAMABAD)|FULERA|JAIPUR|JAMWA RAMGARH|JHOTWARA|KISHANGARH|KOTPUTLI|NARAYANA|PARKOTA|PHAGI|PHULERA (HQ.SAMBHAR)|RENWAL|SAMBHAR SALT LAKE|SANGANER|SHAHPURA|SODALA|SODHALA|TONK PHATAK|VIRAT NAGAR|VIRATNAGAR',
    'blood_group' => 'A+|A-|AB+|AB-|B+|B-|Don`t Know|O+|O-',
    'gender' => 'FEMALE|MALE',
    'occupation' => 'ACCOUNTANCY|AGRICULTURE|Business|CHEMIST|CONSULTANCY|CONTRACTOR|DOCTOR|Entrepreneur|Event Management|FARMER|Home Maker|HOSPITALITY|Military|Other|POLITICIAN|Professional|Retired|Salaried|Self Employed|SERVICE GOVT|SERVICE PRIVATE|Service Provider|Social Service|Spinning Mills|Student|Textile Manufacturing|TRADERS|TRAINER',
    'resident_type' => 'OWNED|RENTED|GOVT. QUARTER',
    'country' => 'BANGLADESH|BHUTAN|CHINA|FRANCE|GERMANY|HOLLAND|INDIA|NEPAL|OTHER|PAKISTAN|SINGAPORE|SRI LANKA|UAE|UNITED KINGDOM|UNITED STATES|UNITED STATES OF AMERICA',
    'marital_status' => 'DIVORCEE|MARRIED|UNMARRIED|WIDOW|WIDOWER',
    'income' => 'Below 1 lakh|1 Lakh+ to 3 Lakh|3 Lakh+ To 6 Lakh|6 Lakh+ To 10 Lakh|Above 10 Lakh|DONT WANT TO DISCLOSE',
    'age_group' => '0-5 Years|6-10 Years|11-15 Years|16-20 Years|21-25 Years|26-30 Years|31-40 Years|41-50 Years|51-60 Years|61-70 Years|71-80 Years|81-90 Years|91-100 Years',
    'archive_type' => 'die|Died|married|Migrated|Naya Chulha|Wrong Entry',
    'designation' => 'ABMM Karyakari Mandal|ABMM Karyasamity|Admin|Arthmantri|Assistant Secretary|Ex National President|Ex National President Mahila|Jila Mantri|Jila President|Jila Secretary|Joint Secretary|Karya Samitee Sadasya|Karyakari Mandal Sadasya|Karyakari Mandal Sadasya (Trust A/C)|Karyalay Mantri|Karyasamiti Member|Mahamantri|Pradesh Adhyaksh|Pradesh Mantri|Pradesh Sachiv|President|Sabhapati|Sah-Sachiv|SAMITI SANYOJAK RASHTRIYA|Sangthan Mantri|Sanyojak|Sanyukta Mantri|Secretary|SHYAM SUNDAR|Tehsil President|Tehsil Secretary|Treasurer|Up Sabhapati|Vice President',
    // Mangal Parinay filters
    'parinay_qualification' => 'ENGINEERING|MEDICAL|PROFESSIONAL|MANAGEMENT|IT PROFESSIONAL|LAW|GRADUATE/POST GRADUATE|NON GRADUATE|OTHER DEGREE|DOESN\'T MATTER',
    'parinay_degree' => 'B.ARCH|B.E./B.TECH|B.PHARM|B.PLAN|BAMS|BBA|BCA|BCOM|BDS|BED|BHM|BHMS|BPTH|BSC|BVSC|CA|CFA|CS|DIPLOMA|DM|DNB|HIGH SCHOOL|ICWA|LLB|LLM|M.ARCH|M.E./M.TECH|M.PHARM|M.PLAN|MA|MBA|MBBS|MCA|MCH|MCOM|MD|MED|MPTH|MS|MSC|MVSC|PGDCA|PGDM',
    'parinay_occupation_type' => 'SERVICE|BUSINESS|PROFESSIONAL|OTHER|DOESN\'T MATTER',
    'parinay_occupation' => 'GOVERNMENT SERVICE|PRIVATE SERVICE|CIVIL SERVICES|ACADEMIC|DEFENCE|INDUSTRIALIST|BUSINESS|CONSULTANT|FREE LANCER|SELF EMPLOYED|CONTRACTOR|FARMER|STUDENT|NOT WORKING',
    'pradesh_availability' => 'Yes|No',
    'parinay_status' => 'Registered|Paid Members|Inactive',
    // Notification form
    'notify_audience' => 'Committee Member|Male|Female|Family Head|Age <40|Age >40',
    'notify_time' => implode('|', $times),
];
