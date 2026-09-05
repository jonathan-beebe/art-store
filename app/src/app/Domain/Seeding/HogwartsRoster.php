<?php

declare(strict_types=1);

namespace App\Domain\Seeding;

/**
 * Every person `App\Domain\Seeding\ActivityPlan` can name, so a ninety-day
 * plan draws on more than the half-dozen sellers and customers `make fresh`
 * already seeded. Every name is Harry Potter universe, and every email is
 * `example.com`, the same rule the rest of the demo data follows. Deliberately
 * excludes Molly Weasley, Dean Thomas, Sybill Trelawney, Colin Creevey,
 * Neville Longbottom, Luna Lovegood, and Hermione Granger — `make fresh`
 * already seeded those emails, so this list stays free to reuse a first name
 * as an email's local part without colliding with one it created.
 */
final class HogwartsRoster
{
    private const string DOMAIN = 'example.com';

    /**
     * name => email local part. A repeated first name (three Longbottoms
     * given the exclusion above, for instance) takes a first-plus-last
     * form instead, so no two entries ever share a local part.
     *
     * @var array<string, string>
     */
    private const array PEOPLE = [
        // Gryffindor
        'Harry Potter' => 'harry',
        'Ron Weasley' => 'ron',
        'Ginny Weasley' => 'ginny',
        'Fred Weasley' => 'fred',
        'George Weasley' => 'george',
        'Percy Weasley' => 'percy',
        'Bill Weasley' => 'bill',
        'Charlie Weasley' => 'charlie',
        'Seamus Finnigan' => 'seamus',
        'Lavender Brown' => 'lavender',
        'Parvati Patil' => 'parvati',
        'Angelina Johnson' => 'angelina',
        'Alicia Spinnet' => 'alicia',
        'Katie Bell' => 'katie',
        'Oliver Wood' => 'oliver',
        'Cormac McLaggen' => 'cormac',
        'Lee Jordan' => 'lee',
        'Romilda Vane' => 'romilda',
        'Demelza Robins' => 'demelza',
        'Ritchie Coote' => 'ritchie',
        'Jimmy Peakes' => 'jimmy',
        'Euan Abercrombie' => 'euan',
        'Natalie McDonald' => 'natalie',
        'Dennis Creevey' => 'dennis',

        // Slytherin
        'Draco Malfoy' => 'draco',
        'Pansy Parkinson' => 'pansy',
        'Vincent Crabbe' => 'vincent',
        'Gregory Goyle' => 'gregory',
        'Blaise Zabini' => 'blaise',
        'Millicent Bulstrode' => 'millicent',
        'Theodore Nott' => 'theodore',
        'Marcus Flint' => 'marcus',
        'Adrian Pucey' => 'adrian',
        'Daphne Greengrass' => 'daphne',
        'Astoria Greengrass' => 'astoria',
        'Graham Montague' => 'graham',

        // Hufflepuff
        'Cedric Diggory' => 'cedric',
        'Susan Bones' => 'susan',
        'Hannah Abbott' => 'hannah',
        'Ernie Macmillan' => 'ernie',
        'Justin Finch-Fletchley' => 'justin',
        'Zacharias Smith' => 'zacharias',
        'Newt Scamander' => 'newt',

        // Ravenclaw
        'Cho Chang' => 'cho',
        'Padma Patil' => 'padma',
        'Michael Corner' => 'michael',
        'Terry Boot' => 'terry',
        'Marietta Edgecombe' => 'marietta',
        'Roger Davies' => 'roger',
        'Penelope Clearwater' => 'penelope',
        'Gilderoy Lockhart' => 'gilderoy',

        // Hogwarts staff
        'Albus Dumbledore' => 'albus',
        'Minerva McGonagall' => 'minerva',
        'Severus Snape' => 'severus',
        'Rubeus Hagrid' => 'rubeus',
        'Filius Flitwick' => 'filius',
        'Pomona Sprout' => 'pomona',
        'Argus Filch' => 'argus',
        'Poppy Pomfrey' => 'poppy',
        'Horace Slughorn' => 'horace',
        'Remus Lupin' => 'remus',
        'Alastor Moody' => 'alastor',
        'Dolores Umbridge' => 'dolores',
        'Quirinus Quirrell' => 'quirinus',
        'Charity Burbage' => 'charity',
        'Aurora Sinistra' => 'aurora',
        'Septima Vector' => 'septima',
        'Bathsheda Babbling' => 'bathsheda',
        'Wilhelmina Grubbly-Plank' => 'wilhelmina',
        'Irma Pince' => 'irma',
        'Rolanda Hooch' => 'rolanda',
        'Cuthbert Binns' => 'cuthbert',

        // Weasleys, the Order of the Phoenix, and family
        'Arthur Weasley' => 'arthur',
        'Sirius Black' => 'sirius',
        'Nymphadora Tonks' => 'nymphadora',
        'Kingsley Shacklebolt' => 'kingsley',
        'Emmeline Vance' => 'emmeline',
        'Dedalus Diggle' => 'dedalus',
        'Elphias Doge' => 'elphias',
        'Hestia Jones' => 'hestia',
        'Mundungus Fletcher' => 'mundungus',
        'Fleur Delacour' => 'fleur',
        'Gabrielle Delacour' => 'gabrielle',
        'Viktor Krum' => 'viktor',
        'Andromeda Tonks' => 'andromeda',
        'Ted Tonks' => 'ted',
        'Xenophilius Lovegood' => 'xenophilius',
        'Regulus Black' => 'regulus',
        'James Potter' => 'james',
        'Lily Potter' => 'lily',
        'Sturgis Podmore' => 'sturgis',
        'Arabella Figg' => 'arabella',
        'Augusta Longbottom' => 'augusta',
        'Alice Longbottom' => 'alice',
        'Frank Bryce' => 'frank',

        // Ministry of Magic and the Wizengamot
        'Cornelius Fudge' => 'cornelius',
        'Rufus Scrimgeour' => 'rufus',
        'Pius Thicknesse' => 'pius',
        'Barty Crouch' => 'barty',
        'Amelia Bones' => 'amelia',
        'Ludo Bagman' => 'ludo',
        'Mafalda Hopkirk' => 'mafalda',
        'Reginald Cattermole' => 'reginald',
        'Griselda Marchbanks' => 'griselda',
        'Tiberius Ogden' => 'tiberius',
        'Bertha Jorkins' => 'bertha',

        // Death Eaters
        'Lucius Malfoy' => 'lucius',
        'Narcissa Malfoy' => 'narcissa',
        'Bellatrix Lestrange' => 'bellatrix',
        'Rodolphus Lestrange' => 'rodolphus',
        'Antonin Dolohov' => 'antonin',
        'Fenrir Greyback' => 'fenrir',
        'Peter Pettigrew' => 'peter',
        'Walden Macnair' => 'walden',
        'Corban Yaxley' => 'corban',
        'Augustus Rookwood' => 'augustus',
        'Evan Rosier' => 'evan',
        'Igor Karkaroff' => 'igor',
        'Amycus Carrow' => 'amycus',
        'Alecto Carrow' => 'alecto',

        // Diagon Alley, Hogsmeade, and beyond
        'Garrick Ollivander' => 'garrick',
        'Florean Fortescue' => 'florean',
        'Tom the Innkeeper' => 'tom',
        'Aberforth Dumbledore' => 'aberforth',
        'Bathilda Bagshot' => 'bathilda',
        'Rita Skeeter' => 'rita',
        'Amos Diggory' => 'amos',
        'Griphook' => 'griphook',
        'Dobby' => 'dobby',
        'Kreacher' => 'kreacher',
        'Winky' => 'winky',
        'Firenze' => 'firenze',
        'Bane' => 'bane',
        'Grawp' => 'grawp',
    ];

    private function __construct() {} // @codeCoverageIgnore

    /**
     * Every roster entry, in a fixed order so `ActivityPlan` can address one
     * by a stable index.
     *
     * @return list<array{name: string, email: string}>
     */
    public static function people(): array
    {
        // array_map() over more than one array always reindexes the result
        // sequentially from 0, regardless of PEOPLE's own string keys — the
        // list this already returns.
        return array_map(
            fn (string $localPart, string $name): array => ['name' => $name, 'email' => "{$localPart}@".self::DOMAIN],
            self::PEOPLE,
            array_keys(self::PEOPLE),
        );
    }
}
