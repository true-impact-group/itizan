<?php

namespace App\Http\Controllers\Site;

use App\Enums\PractitionerType;
use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Package;
use App\Models\Page;
use App\Models\Practitioner;
use App\Services\PackageComparison;
use Illuminate\Contracts\View\View;

/** The public marketing site. Read-only, cached-friendly pages. */
class PageController extends Controller
{
    public function __construct(private readonly PackageComparison $comparison) {}

    public function home(): View
    {
        $packages = $this->packages();

        return view('site.home', [
            'packages' => $packages,
            'savings' => $this->comparison->longestPackageSavings($packages),
            'doctors' => $this->verifiedTeam(PractitionerType::Doctor, 4),
            'faqs' => Faq::query()->published()->limit(5)->get(),
        ]);
    }

    public function howItWorks(): View
    {
        return view('site.how-it-works');
    }

    public function services(): View
    {
        return view('site.services');
    }

    public function team(): View
    {
        return view('site.team', [
            'doctors' => $this->verifiedTeam(PractitionerType::Doctor),
            'dietitians' => $this->verifiedTeam(PractitionerType::Dietitian),
        ]);
    }

    public function pricing(): View
    {
        $packages = $this->packages();

        return view('site.pricing', [
            'packages' => $packages,
            'savings' => $this->comparison->longestPackageSavings($packages),
        ]);
    }

    public function faq(): View
    {
        return view('site.faq', ['faqs' => Faq::query()->published()->get()]);
    }

    public function prescriptionJourney(): View
    {
        return view('site.prescription-journey');
    }

    public function about(): View
    {
        return view('site.about');
    }

    public function contact(): View
    {
        return view('site.contact');
    }

    public function policies(): View
    {
        return view('site.policies', [
            'pages' => Page::query()->published()->where('group', 'policy')->orderBy('sort_order')->get(),
        ]);
    }

    public function policy(Page $page): View
    {
        abort_unless($page->is_published, 404);

        return view('site.policy', ['page' => $page]);
    }

    private function packages()
    {
        return Package::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    private function verifiedTeam(PractitionerType $type, ?int $limit = null)
    {
        return Practitioner::query()
            ->verified()
            ->ofType($type)
            ->with('user')
            ->orderBy('sort_order')
            ->when($limit, fn ($query) => $query->limit($limit))
            ->get();
    }
}
