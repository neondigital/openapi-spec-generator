<?php

namespace LaravelJsonApi\OpenApiSpec\Tests\Support\Database\Seeders;

use Illuminate\Database\Seeder;
use LaravelJsonApi\OpenApiSpec\Tests\Support\Models\Category;
use LaravelJsonApi\OpenApiSpec\Tests\Support\Models\Comment;
use LaravelJsonApi\OpenApiSpec\Tests\Support\Models\Post;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();

        $category = Category::create([
            'name' => 'News'
        ]);

        $post1 = Post::create([
            'title' => 'My lovely blog post',
            'category_id' => $category->id,
        ]);

        $post2 = Post::create([
            'title' => 'Another blog post',
            'category_id' => $category->id,
        ]);

        Comment::create([
           'post_id' => $post1->id,
           'message' => 'This is a fair comment',
        ]);

        Comment::create([
           'post_id' => $post1->id,
           'message' => 'I just came for the comments',
        ]);

        Comment::create([
           'post_id' => $post1->id,
           'message' => 'Great blog post!',
        ]);
    }
}
