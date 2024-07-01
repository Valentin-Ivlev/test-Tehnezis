<?php

namespace App\Controller;

use League\Uri\Http;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DomCrawler\Crawler;


class ImageCrawlerController extends AbstractController
{
    public function index(): Response
    {
        return $this->render('index.html.twig');
    }

    public function crawl(Request $request, HttpClientInterface $httpClient): Response
    {
        $pageUrl = $request->query->get('url');

        $response = $httpClient->request('GET', $pageUrl);
        $content = $response->getContent();

        $crawler = new Crawler($content);

        $images = $crawler->filter('img')->extract(['src']);

        $backgroundImages = $crawler->filter('[style*="background-image"]')->extract(['style']);
        $backgroundUrls = array_map(function ($style) {
            preg_match('/background-image:\s*url\([\'"]?(.+?)[\'"]?\)/i', $style, $matches);
            return $matches[1] ?? null;
        }, $backgroundImages);
        $backgroundUrls = array_filter($backgroundUrls);

        $contentImages = $crawler->filter('[style*="content"]')->extract(['style']);
        $contentUrls = array_map(function ($style) {
            preg_match('/content:\s*url\([\'"]?(.+?)[\'"]?\)/i', $style, $matches);
            return $matches[1] ?? null;
        }, $contentImages);
        $contentUrls = array_filter($contentUrls);

        $allImages = array_merge($images, $backgroundUrls, $contentUrls);

        $baseUri = Http::createFromString($pageUrl);

        $absoluteImages = array_map(function ($image) use ($baseUri) {
            $path = strpos($image, '/') === 0 ? $image : '/' . $image;
            return (string) $baseUri->withPath($path);
        }, $allImages);

        $totalSize = 0;
        foreach ($absoluteImages as $image) {
            $response = $httpClient->request('HEAD', $image);
            $totalSize += $response->getHeaders()['content-length'][0] ?? 0;
        }

        $totalSizeMb = $totalSize > 0 ? $totalSize / 1024 / 1024 : 0;

        return $this->render('result.html.twig', [
            'images' => array_chunk($absoluteImages, 4),
            'totalCount' => count($absoluteImages),
            'totalSize' => (float) $totalSizeMb,
        ]);
    }
}