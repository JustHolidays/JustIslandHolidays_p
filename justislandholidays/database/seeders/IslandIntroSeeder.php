<?php

namespace Database\Seeders;

use App\Models\Island;
use Illuminate\Database\Seeder;

/**
 * Starting introductions for the island pages, in English (drafted 19 Nov 2026).
 * They are a first draft to edit in the admin (Islands and resorts > the island).
 * An island whose English intro is already filled in is never touched, so text
 * edited in the admin is safe when the seeders run again on deploy.
 * Spanish and German follow in phase 2, checked by native speakers.
 */
class IslandIntroSeeder extends Seeder
{
    public const INTROS = [
        'menorca' => <<<'MD'
Menorca is the quiet, green island of the Balearics. It is small enough to cross in under an hour, yet its coast hides more than 70 beaches and coves, from long sandy bays in the south to wild red-sand coves in the north. The whole island has been a UNESCO Biosphere Reserve since 1993, and much of it still looks the way it did decades ago: stone walls, farmland, pine woods and white villages.

## Where to stay

The south coast has the gentle, sheltered beaches that suit families, such as Son Bou, the island's longest beach, and Cala Galdana. The north is rockier and more dramatic, with fewer people. Maó, the capital, sits on one of the largest natural harbours in the Mediterranean. Ciutadella, in the west, has an old town of narrow streets, a small harbour lined with restaurants and some of the most famous coves on the island close by, including Cala Macarella and Cala Turqueta.

## Things to do

- Walk part of the Camí de Cavalls, a 185 km path that follows the whole coast.
- Visit the prehistoric Talayotic sites, now a UNESCO World Heritage Site.
- Drive up Monte Toro, the highest point, for views across the island.
- Try Mahón cheese and the local gin, made in Maó since the 18th century.

## Getting there

Menorca Airport is just outside Maó, with direct flights from the UK in the summer. Ferries also run from Barcelona, Valencia and Mallorca. The main season runs from May to October, and June and September are quieter and still warm.
MD,

        'mallorca' => <<<'MD'
Mallorca is the largest of the Balearic Islands, and one of the most varied places to spend a holiday in the Mediterranean. In a single week you can swim from a white-sand beach, walk in the mountains, wander a stone village and eat out in a lively city.

## Where to stay

The north-west is dominated by the Serra de Tramuntana, a mountain range that is a UNESCO World Heritage Site. Its villages, such as Sóller, Deià and Valldemossa, are full of character, with stone houses and terraced olive groves. The north, around Pollença and Alcúdia, has wide sandy bays that suit families. The east coast is lined with small coves of clear water, such as Cala Mondragó. Inland, the countryside is dotted with fincas: old farmhouses, many now holiday homes with pools and space to relax.

## Things to do

- Explore Palma, the capital, with its Gothic cathedral, old town and harbour.
- Ride the wooden train from Palma to Sóller, running since 1912.
- Drive or cycle out to Cap de Formentor for sea views and quiet beaches.
- Visit the Coves del Drac, caves with an underground lake near Porto Cristo.
- Walk in the Tramuntana, from short strolls to the long-distance GR 221 route.

## Getting there

Palma Airport has direct flights from most UK airports all year, and is around 20 minutes from the city. Ferries run from Barcelona, Valencia and Dénia. The beach season runs from May to October. Spring and autumn are good for walking and cycling.
MD,

        'ibiza' => <<<'MD'
Ibiza is famous for its nightlife, but there is far more to the island than clubs. Away from the busy resorts you find pine-covered hills, whitewashed villages, quiet coves and some of the clearest water in the Mediterranean.

## Where to stay

Ibiza Town and Playa d'en Bossa are the liveliest areas, with restaurants, bars and beach clubs. Sant Antoni, in the west, is known for its sunsets. For a quieter holiday, look to the north and east, around Sant Joan, Portinatx and Santa Eulària, where villas sit among farmland and woods.

## Things to do

- Walk the walls of Dalt Vila, the old town, part of a UNESCO World Heritage Site.
- Watch the sunset over the rock island of Es Vedrà.
- Browse the markets at Las Dalias and Punta Arabí.
- Take the ferry to Formentera for the day.

## Getting there

Ibiza Airport is close to Ibiza Town, with direct flights from the UK, mostly between spring and autumn. Ferries run from Barcelona, Valencia, Dénia and Mallorca.
MD,

        'formentera' => <<<'MD'
Formentera is the smallest of the inhabited Balearic Islands, and the most laid back. It has no airport, so it never feels crowded, and life moves slowly. Its beaches of fine white sand and turquoise water are among the best in Europe.

## Where to stay

Most holiday homes are spread across the flat centre of the island, around Sant Francesc, Sant Ferran and Es Pujols, a short ride from the beaches. La Mola, on the high eastern end, is quieter still.

## Things to do

- Swim at Ses Illetes, in the Ses Salines Natural Park.
- Cycle the island's green routes, quiet tracks through farmland and salt flats.
- Visit the lighthouses at Cap de Barbaria and La Mola.
- Snorkel over the seagrass meadows that keep the water so clear.

## Getting there

Fly to Ibiza, then take the ferry from Ibiza Town, which takes about 30 minutes. Many visitors hire a bike or a small car once they arrive.
MD,
    ];

    public function run(): void
    {
        foreach (self::INTROS as $slug => $text) {
            $island = Island::where('slug', $slug)->first();
            if (! $island || filled($island->intro['en'] ?? null)) {
                continue;   // missing, or already written or edited in the admin
            }

            $island->intro = array_merge($island->intro ?? [], ['en' => trim($text)]);
            $island->save();
        }
    }
}
