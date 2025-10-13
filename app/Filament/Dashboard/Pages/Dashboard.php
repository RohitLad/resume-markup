<?php
namespace App\Filament\Dashboard\Pages;

use App\Services\ResumeProcessingService;
use App\Models\Profile;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use BackedEnum;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Storage;

class Dashboard extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.dashboard.pages.dashboard';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    public ?array $data = [];
    public bool $showForm = false;

    public function mount(): void
    {
        $profile = Profile::where('user_id', auth()->id())->first();

        if ($profile && !empty($profile->data)) {
            $this->data = $profile->data;
            $this->showForm = true;
        } else {
            $this->data = [];
            $this->showForm = false;
        }

        $this->form->fill($this->data);
    }

    public function form($form)
    {
        return $form
            ->schema([
                FileUpload::make('resume')
                    ->label('Upload Resume (PDF)')
                    ->directory('resumes')
                    ->disk('public')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(10240)
                    ->afterStateUpdated(function ($state) {
                        if (!$state) {
                            return;
                        }

                        try {
                            // Get the temporary uploaded file
                            $temporaryUpload = is_array($state) ? $state[0] : $state;

                            if ($temporaryUpload instanceof TemporaryUploadedFile) {
                                $tempPath = $temporaryUpload->getRealPath();

                                // Store the file permanently
                                $fileName = time() . '_' . $temporaryUpload->getClientOriginalName();
                                $storedPath = Storage::disk('public')->putFileAs('resumes', $temporaryUpload, $fileName);

                                // Initiate resume parsing
                                $processingService = app(ResumeProcessingService::class);
                                $processingService->initiateResumeParsing(auth()->id(), $storedPath);

                                Notification::make()
                                    ->success()
                                    ->title('Resume Uploaded')
                                    ->body('Your resume is being processed.')
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            \Log::error('Resume upload failed', [
                                'error' => $e->getMessage(),
                                'user_id' => auth()->id(),
                            ]);

                            Notification::make()
                                ->danger()
                                ->title('Upload Failed')
                                ->body('Failed to upload resume.')
                                ->send();
                        }
                    })
                    ->helperText('Upload your resume PDF and it will be automatically parsed to populate the form')
                    ->columnSpanFull(),

                Tabs::make('Resume Sections')
                    ->tabs([
                        // Personal Information Tab
                        Tabs\Tab::make('Personal')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('basics.name')
                                            ->label('Full Name')
                                            ->required()
                                            ->placeholder('John Doe')
                                            ->prefixIcon('heroicon-o-user'),

                                        TextInput::make('basics.label')
                                            ->label('Professional Title')
                                            ->placeholder('Senior Software Engineer')
                                            ->prefixIcon('heroicon-o-briefcase'),

                                        TextInput::make('basics.email')
                                            ->label('Email')
                                            ->email()
                                            ->placeholder('john@example.com')
                                            ->prefixIcon('heroicon-o-envelope'),

                                        TextInput::make('basics.phone')
                                            ->label('Phone')
                                            ->tel()
                                            ->placeholder('+1 (555) 123-4567')
                                            ->prefixIcon('heroicon-o-phone'),

                                        TextInput::make('basics.url')
                                            ->label('Website')
                                            ->url()
                                            ->placeholder('https://johndoe.com')
                                            ->prefixIcon('heroicon-o-globe-alt'),

                                        TextInput::make('basics.image')
                                            ->label('Profile Image URL')
                                            ->url()
                                            ->placeholder('https://example.com/photo.jpg')
                                            ->prefixIcon('heroicon-o-photo'),
                                    ]),

                                Textarea::make('basics.summary')
                                    ->label('Professional Summary')
                                    ->rows(4)
                                    ->placeholder('Write a brief summary about yourself and your professional experience...')
                                    ->columnSpanFull(),

                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('basics.location.address')
                                            ->label('Address')
                                            ->placeholder('123 Main Street'),

                                        TextInput::make('basics.location.city')
                                            ->label('City')
                                            ->placeholder('New York'),

                                        TextInput::make('basics.location.region')
                                            ->label('State/Region')
                                            ->placeholder('NY'),

                                        TextInput::make('basics.location.postalCode')
                                            ->label('Postal Code')
                                            ->placeholder('10001'),

                                        TextInput::make('basics.location.countryCode')
                                            ->label('Country Code')
                                            ->maxLength(2)
                                            ->placeholder('US'),
                                    ]),

                                Repeater::make('basics.profiles')
                                    ->label('Social Profiles')
                                    ->schema([
                                        TextInput::make('network')
                                            ->label('Network')
                                            ->placeholder('LinkedIn, Twitter, GitHub, etc.')
                                            ->required(),

                                        TextInput::make('username')
                                            ->label('Username')
                                            ->placeholder('johndoe'),

                                        TextInput::make('url')
                                            ->label('Profile URL')
                                            ->url()
                                            ->placeholder('https://linkedin.com/in/johndoe'),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(0)
                                    ->addActionLabel('Add Social Profile')
                                    ->columnSpanFull()
                                    ->itemLabel(fn(array $state): ?string => $state['network'] ?? 'Social Profile'),
                            ]),

                        // Work Experience Tab
                        Tabs\Tab::make('Experience')
                            ->icon('heroicon-o-briefcase')
                            ->badge(fn() => count($this->data['work'] ?? []))
                            ->schema([
                                Repeater::make('work')
                                    ->label('Work Experience')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Company Name')
                                                    ->required()
                                                    ->placeholder('Acme Corporation'),

                                                TextInput::make('position')
                                                    ->label('Position')
                                                    ->required()
                                                    ->placeholder('Senior Software Engineer'),

                                                TextInput::make('url')
                                                    ->label('Company Website')
                                                    ->url()
                                                    ->placeholder('https://acme.com'),

                                                Grid::make(2)
                                                    ->schema([
                                                        DatePicker::make('startDate')
                                                            ->label('Start Date')
                                                            ->native(false),

                                                        DatePicker::make('endDate')
                                                            ->label('End Date')
                                                            ->native(false)
                                                            ->placeholder('Present'),
                                                    ]),
                                            ]),

                                        Textarea::make('summary')
                                            ->label('Description')
                                            ->rows(3)
                                            ->placeholder('Describe your role and responsibilities...')
                                            ->columnSpanFull(),

                                        TagsInput::make('highlights')
                                            ->label('Key Achievements')
                                            ->placeholder('Add achievement and press Enter')
                                            ->helperText('Press Enter after typing each achievement')
                                            ->columnSpanFull(),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Add Work Experience')
                                    ->collapsible()
                                    ->itemLabel(
                                        fn(array $state): ?string =>
                                        ($state['position'] ?? 'Position') . ' at ' . ($state['name'] ?? 'Company')
                                    )
                                    ->columnSpanFull()
                                    ->reorderable(),
                            ]),

                        // Education Tab
                        Tabs\Tab::make('Education')
                            ->icon('heroicon-o-academic-cap')
                            ->badge(fn() => count($this->data['education'] ?? []))
                            ->schema([
                                Repeater::make('education')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('institution')
                                                    ->label('Institution')
                                                    ->required()
                                                    ->placeholder('University of Example'),

                                                TextInput::make('url')
                                                    ->label('Institution Website')
                                                    ->url()
                                                    ->placeholder('https://university.edu'),

                                                TextInput::make('area')
                                                    ->label('Field of Study')
                                                    ->placeholder('Computer Science'),

                                                TextInput::make('studyType')
                                                    ->label('Degree Type')
                                                    ->placeholder('Bachelor of Science'),

                                                DatePicker::make('startDate')
                                                    ->label('Start Date')
                                                    ->native(false),

                                                DatePicker::make('endDate')
                                                    ->label('End Date')
                                                    ->native(false),

                                                TextInput::make('score')
                                                    ->label('GPA/Score')
                                                    ->placeholder('3.8/4.0'),
                                            ]),

                                        TagsInput::make('courses')
                                            ->label('Relevant Courses')
                                            ->placeholder('Add course and press Enter')
                                            ->columnSpanFull(),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Add Education')
                                    ->collapsible()
                                    ->itemLabel(
                                        fn(array $state): ?string =>
                                        ($state['studyType'] ?? 'Degree') . ' - ' . ($state['institution'] ?? 'Institution')
                                    )
                                    ->columnSpanFull()
                                    ->reorderable(),
                            ]),

                        // Skills Tab
                        Tabs\Tab::make('Skills')
                            ->icon('heroicon-o-sparkles')
                            ->badge(fn() => count($this->data['skills'] ?? []))
                            ->schema([
                                Repeater::make('skills')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Skill Category')
                                                    ->required()
                                                    ->placeholder('Programming Languages'),

                                                TextInput::make('level')
                                                    ->label('Proficiency Level')
                                                    ->placeholder('Advanced')
                                                    ->datalist([
                                                        'Beginner',
                                                        'Intermediate',
                                                        'Advanced',
                                                        'Expert',
                                                        'Master',
                                                    ]),
                                            ]),

                                        TagsInput::make('keywords')
                                            ->label('Skills')
                                            ->placeholder('Add skill and press Enter')
                                            ->helperText('Add individual skills in this category')
                                            ->columnSpanFull(),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Add Skill Category')
                                    ->collapsible()
                                    ->itemLabel(
                                        fn(array $state): ?string =>
                                        ($state['name'] ?? 'Skill Category') .
                                        ($state['level'] ? ' (' . $state['level'] . ')' : '')
                                    )
                                    ->columnSpanFull()
                                    ->reorderable(),
                            ]),

                        // Projects Tab
                        Tabs\Tab::make('Projects')
                            ->icon('heroicon-o-rocket-launch')
                            ->badge(fn() => count($this->data['projects'] ?? []))
                            ->schema([
                                Repeater::make('projects')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Project Name')
                                                    ->required()
                                                    ->placeholder('Awesome Project'),

                                                TextInput::make('url')
                                                    ->label('Project URL')
                                                    ->url()
                                                    ->placeholder('https://github.com/user/project'),

                                                DatePicker::make('startDate')
                                                    ->label('Start Date')
                                                    ->native(false),

                                                DatePicker::make('endDate')
                                                    ->label('End Date')
                                                    ->native(false)
                                                    ->placeholder('Ongoing'),
                                            ]),

                                        Textarea::make('description')
                                            ->label('Description')
                                            ->rows(3)
                                            ->placeholder('Describe the project, your role, and technologies used...')
                                            ->columnSpanFull(),

                                        TagsInput::make('highlights')
                                            ->label('Key Highlights')
                                            ->placeholder('Add highlight and press Enter')
                                            ->columnSpanFull(),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Add Project')
                                    ->collapsible()
                                    ->itemLabel(fn(array $state): ?string => $state['name'] ?? 'Project')
                                    ->columnSpanFull()
                                    ->reorderable(),
                            ]),

                        // Certificates & Awards Tab
                        Tabs\Tab::make('Certificates & Awards')
                            ->icon('heroicon-o-trophy')
                            ->badge(fn() => count($this->data['certificates'] ?? []) + count($this->data['awards'] ?? []))
                            ->schema([
                                Repeater::make('certificates')
                                    ->label('Certificates')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Certificate Name')
                                                    ->required()
                                                    ->placeholder('AWS Certified Solutions Architect'),

                                                TextInput::make('issuer')
                                                    ->label('Issuing Organization')
                                                    ->placeholder('Amazon Web Services'),

                                                DatePicker::make('date')
                                                    ->label('Issue Date')
                                                    ->native(false),

                                                TextInput::make('url')
                                                    ->label('Certificate URL')
                                                    ->url()
                                                    ->placeholder('https://credentials.example.com')
                                                    ->columnSpan(3),
                                            ]),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Add Certificate')
                                    ->collapsible()
                                    ->itemLabel(fn(array $state): ?string => $state['name'] ?? 'Certificate')
                                    ->columnSpanFull()
                                    ->reorderable(),

                                Repeater::make('awards')
                                    ->label('Awards')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextInput::make('title')
                                                    ->label('Award Title')
                                                    ->required()
                                                    ->placeholder('Employee of the Year'),

                                                TextInput::make('awarder')
                                                    ->label('Awarded By')
                                                    ->placeholder('Acme Corporation'),

                                                DatePicker::make('date')
                                                    ->label('Date')
                                                    ->native(false),
                                            ]),

                                        Textarea::make('summary')
                                            ->label('Description')
                                            ->rows(2)
                                            ->placeholder('Describe the award and why you received it...')
                                            ->columnSpanFull(),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Add Award')
                                    ->collapsible()
                                    ->itemLabel(fn(array $state): ?string => $state['title'] ?? 'Award')
                                    ->columnSpanFull()
                                    ->reorderable(),
                            ]),

                        // Additional Info Tab
                        Tabs\Tab::make('Additional')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Repeater::make('languages')
                                    ->label('Languages')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('language')
                                                    ->label('Language')
                                                    ->required()
                                                    ->placeholder('English'),

                                                TextInput::make('fluency')
                                                    ->label('Fluency Level')
                                                    ->placeholder('Native')
                                                    ->datalist([
                                                        'Native',
                                                        'Fluent',
                                                        'Professional',
                                                        'Intermediate',
                                                        'Basic',
                                                    ]),
                                            ]),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Add Language')
                                    ->itemLabel(
                                        fn(array $state): ?string =>
                                        ($state['language'] ?? 'Language') .
                                        ($state['fluency'] ? ' - ' . $state['fluency'] : '')
                                    )
                                    ->columnSpanFull()
                                    ->reorderable(),

                                Repeater::make('volunteer')
                                    ->label('Volunteer Experience')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('organization')
                                                    ->label('Organization')
                                                    ->required()
                                                    ->placeholder('Red Cross'),

                                                TextInput::make('position')
                                                    ->label('Role')
                                                    ->placeholder('Volunteer Coordinator'),

                                                TextInput::make('url')
                                                    ->label('Organization Website')
                                                    ->url()
                                                    ->placeholder('https://organization.org'),

                                                Grid::make(2)
                                                    ->schema([
                                                        DatePicker::make('startDate')
                                                            ->label('Start Date')
                                                            ->native(false),

                                                        DatePicker::make('endDate')
                                                            ->label('End Date')
                                                            ->native(false),
                                                    ]),
                                            ]),

                                        Textarea::make('summary')
                                            ->label('Description')
                                            ->rows(2)
                                            ->placeholder('Describe your volunteer work...')
                                            ->columnSpanFull(),

                                        TagsInput::make('highlights')
                                            ->label('Key Contributions')
                                            ->placeholder('Add contribution and press Enter')
                                            ->columnSpanFull(),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Add Volunteer Experience')
                                    ->collapsible()
                                    ->itemLabel(
                                        fn(array $state): ?string =>
                                        ($state['position'] ?? 'Role') . ' at ' . ($state['organization'] ?? 'Organization')
                                    )
                                    ->columnSpanFull()
                                    ->reorderable(),

                                Repeater::make('publications')
                                    ->label('Publications')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Publication Title')
                                                    ->required()
                                                    ->placeholder('My Research Paper'),

                                                TextInput::make('publisher')
                                                    ->label('Publisher')
                                                    ->placeholder('IEEE'),

                                                DatePicker::make('releaseDate')
                                                    ->label('Release Date')
                                                    ->native(false),

                                                TextInput::make('url')
                                                    ->label('Publication URL')
                                                    ->url()
                                                    ->placeholder('https://doi.org/...'),
                                            ]),

                                        Textarea::make('summary')
                                            ->label('Summary')
                                            ->rows(2)
                                            ->placeholder('Brief summary of the publication...')
                                            ->columnSpanFull(),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Add Publication')
                                    ->collapsible()
                                    ->itemLabel(fn(array $state): ?string => $state['name'] ?? 'Publication')
                                    ->columnSpanFull()
                                    ->reorderable(),

                                Repeater::make('interests')
                                    ->label('Interests')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Interest')
                                                    ->required()
                                                    ->placeholder('Artificial Intelligence'),

                                                TagsInput::make('keywords')
                                                    ->label('Keywords')
                                                    ->placeholder('Add keyword and press Enter'),
                                            ]),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Add Interest')
                                    ->itemLabel(fn(array $state): ?string => $state['name'] ?? 'Interest')
                                    ->columnSpanFull()
                                    ->reorderable(),

                                Repeater::make('references')
                                    ->label('References')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Reference Name')
                                            ->required()
                                            ->placeholder('Jane Smith'),

                                        Textarea::make('reference')
                                            ->label('Reference Text')
                                            ->rows(2)
                                            ->placeholder('What this person says about you...')
                                            ->columnSpanFull(),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Add Reference')
                                    ->itemLabel(fn(array $state): ?string => $state['name'] ?? 'Reference')
                                    ->columnSpanFull()
                                    ->reorderable(),
                            ]),
                    ])
                    ->contained(false)
                    ->persistTabInQueryString()
                    ->visible($this->showForm)
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Resume Data')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->action(function () {
                    $formData = $this->form->getState();

                    // Remove resume from form data before saving (it's not part of profile data)
                    unset($formData['resume']);

                    Profile::updateOrCreate(
                        ['user_id' => auth()->id()],
                        ['data' => $formData]
                    );

                    Notification::make()
                        ->success()
                        ->title('Saved')
                        ->body('Resume data saved successfully.')
                        ->send();
                })
                ->visible($this->showForm)
                ->requiresConfirmation(false),
        ];
    }
}