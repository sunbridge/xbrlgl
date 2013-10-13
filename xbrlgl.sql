/*
 * create user xbrl;
 * alter role xbrl with password 'gl';
 * alter role xbrl with login;
 * create database xbrlgl owner xbrl encoding'UTF8';
 */

-- accountingEnrties
CREATE TABLE accountingEnrties (
    -- documentInfo
    id SERIAL8 NOT NULL,
    entriesType VARCHAR(32),
    uniqueID VARCHAR(32),
    language VARCHAR(32),
    creationDate  VARCHAR(10),
    creator  VARCHAR(64),
    entriesComment  VARCHAR(128),
    periodCoveredStart VARCHAR(10),
    periodCoveredEnd  VARCHAR(10),
    sourceApplication  VARCHAR(32),
    defaultCurrency  VARCHAR(32),
    -- entityInformation
    -- entityPhoneNumber 
    phoneNumber VARCHAR(16),
    -- entityFaxNumberStructure
    entityFaxNumber VARCHAR(16), 
    -- entityEmailAddressStructure 
    entityEmailAddress  VARCHAR(64),
    -- organizationIdentifiers 
    organizationIdentifier VARCHAR(16),
    organizationDescription VARCHAR(128),
    -- organizationAddress 
    organizationAddressStreet VARCHAR(128),
    organizationAddressCity VARCHAR(128), 
    organizationAddressStateOrProvince VARCHAR(128), 
    organizationAddressCountry VARCHAR(128),
    organizationAddressZipOrPostalCode VARCHAR(16), 
    PRIMARY KEY (id)
);

-- entryHeader
CREATE TABLE entryHeaders (
    id SERIAL8 NOT NULL,
    accountingEnrtiesID INT8,
    postedDate VARCHAR(10),
    enteredBy VARCHAR(32),
    enteredDate VARCHAR(10),
    sourceJournalID VARCHAR(16),
    sourceJournalDescription VARCHAR(128),
    entryOrigin VARCHAR(64),
    entryType VARCHAR(10),
    entryNumber VARCHAR(16),
    entryComment VARCHAR(16),
    bookTaxDifference VARCHAR(32),
    PRIMARY KEY (id),
    FOREIGN KEY (accountingEnrtiesID)  REFERENCES accountingEnrties ( id )
);

-- entryDetail
CREATE TABLE entryDetails (
    id SERIAL8 NOT NULL,
    entryHeadersID INT8,			
    lineNumber VARCHAR(16),
    -- account
    accountMainID VARCHAR(32),
    accountMainDescription VARCHAR(128),
    accountPurposeCode VARCHAR(16),
    accountType VARCHAR(32),
    -- accountSub
    accountSubDescription VARCHAR(128),
    accountSubID VARCHAR(32),
    accountSubType VARCHAR(32),
    amount VARCHAR(16),
    amountDecimals VARCHAR(16),
    amountUnitref  VARCHAR(16),
    amountMemo VARCHAR(128),
    -- identifierReference
    identifierCode VARCHAR(16),
    -- identifierExternalReference
    identifierAuthorityCode VARCHAR(16),
    identifierAuthority VARCHAR(64),
    identifierDescription VARCHAR(128),
    identifierType VARCHAR(32),
    -- identifierAddress
    identifierStreet VARCHAR(128),
    identifierCity VARCHAR(128),
    identifierStateOrProvince VARCHAR(128),
    identifierCountry VARCHAR(128),
    identifierZipOrPostalCode VARCHAR(16),
    documentType VARCHAR(16),
    documentNumber VARCHAR(16),
    documentReference VARCHAR(16),
    documentDate VARCHAR(10),
    documentLocation VARCHAR(64),
    maturityDate VARCHAR(10),
    terms VARCHAR(32),
    -- measurable
    measurableCode VARCHAR(16),
    measurableID VARCHAR(64),
    measurableDescription VARCHAR(128),
    measurableQuantity  VARCHAR(64),
    measurableUnitOfMeasure VARCHAR(16),
    measurableCostPerUnit  VARCHAR(64),
    measurableQualifier VARCHAR(16),
    -- depreciationMortgage
    dmJurisdiction VARCHAR(32),
    -- taxes
    taxAuthority  VARCHAR(16),
    taxAmount  VARCHAR(64),
    taxCode VARCHAR(16),
    debitCreditCode VARCHAR(16),
    postingDate VARCHAR(10),
    postingStatus VARCHAR(16),
    detailComment VARCHAR(128),
    PRIMARY KEY (id),
    FOREIGN KEY (entryHeadersID)  REFERENCES entryHeaders ( id )
);
