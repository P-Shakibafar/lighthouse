<?php

namespace Tests\Integration\Schema\Directives;

use function factory;
use Illuminate\Support\Facades\DB;
use Tests\DBTestCase;
use Tests\Utils\Models\Author;
use Tests\Utils\Models\Book;

class WithMinDirectiveTest extends DBTestCase
{
    public function testEagerLoadsRelationMin(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            authors: [Author!] @all
        }

        type Book {
            title: String!
            price: Int!
        }

        type Author {
            name: String!
            books:[Book!]! @belongsToMany
            books_min_price: Int!
                @withMin(relation: "books", column: "price")
        }
        ';

        [$author1,$author2,$author3] = factory(Author::class, 3)->create();
        $book1 = factory(Book::class)->create(['price'=>10]);
        $book2 = factory(Book::class)->create(['price'=>20]);
        $book3 = factory(Book::class)->create(['price'=>30]);
        $author1->books()->attach([
            $book1->id, $book2->id,
        ]);
        $author2->books()->attach([
            $book2->id, $book3->id,
        ]);
        $author3->books()->attach([
            $book1->id, $book2->id, $book3->id,
        ]);
        $queries = 0;
        DB::listen(function ($q) use (&$queries): void {
            $queries++;
        });

        $this->graphQL(/** @lang GraphQL */ '
        {
            authors {
                books_min_price
            }
        }
        ')->assertExactJson([
            'data' => [
                'authors' => [
                    [
                        'books_min_price' => 10,
                    ],
                    [
                        'books_min_price' => 20,
                    ],
                    [
                        'books_min_price' => 10,
                    ],
                ],
            ],
        ]);

        $this->assertSame(2, $queries);
    }
}