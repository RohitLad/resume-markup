<?php
namespace App\Filament\Dashboard\Pages;

use App\Models\Profile;
use App\Services\OpenAIService;
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
use Filament\Schemas\Components\Section;

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
            $this->data = $this->getEmptyStructure();
            $this->showForm = false;
        }
        
        $this->form->fill($this->data);
    }

    public function form( $form)
    {
        return $form
            ->schema([
                FileUpload::make('resume')
                    ->label('Upload Resume (PDF)')
                    ->directory('resumes')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(10240)
                    ->afterStateUpdated(function ($state) {
                        if ($state) {
                            $this->processResumeFile($state);
                        }
                    })
                    ->live()
                    ->columnSpanFull(),

                // Basics Section
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('basics.name')
                            ->label('Full Name')
                            ->required(),
                        TextInput::make('basics.label')
                            ->label('Professional Title'),
                        TextInput::make('basics.email')
                            ->label('Email')
                            ->email(),
                        TextInput::make('basics.phone')
                            ->label('Phone')
                            ->tel(),
                        TextInput::make('basics.url')
                            ->label('Website')
                            ->url(),
                        TextInput::make('basics.image')
                            ->label('Profile Image URL')
                            ->url(),
                        Textarea::make('basics.summary')
                            ->label('Professional Summary')
                            ->rows(4)
                            ->columnSpanFull(),
                        
                        // Location
                        Section::make('Location')
                            ->schema([
                                TextInput::make('basics.location.address')
                                    ->label('Address'),
                                TextInput::make('basics.location.city')
                                    ->label('City'),
                                TextInput::make('basics.location.region')
                                    ->label('State/Region'),
                                TextInput::make('basics.location.postalCode')
                                    ->label('Postal Code'),
                                TextInput::make('basics.location.countryCode')
                                    ->label('Country Code')
                                    ->maxLength(2),
                            ])
                            ->columns(2)
                            ->collapsible(),
                        
                        // Social Profiles
                        Repeater::make('basics.profiles')
                            ->label('Social Profiles')
                            ->schema([
                                TextInput::make('network')
                                    ->label('Network')
                                    ->placeholder('LinkedIn, Twitter, GitHub, etc.'),
                                TextInput::make('username')
                                    ->label('Username'),
                                TextInput::make('url')
                                    ->label('Profile URL')
                                    ->url(),
                            ])
                            ->columns(3)
                            ->collapsible()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible($this->showForm),

                // Work Experience
                Section::make('Work Experience')
                    ->schema([
                        Repeater::make('work')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Company Name')
                                    ->required(),
                                TextInput::make('position')
                                    ->label('Position')
                                    ->required(),
                                TextInput::make('url')
                                    ->label('Company Website')
                                    ->url(),
                                DatePicker::make('startDate')
                                    ->label('Start Date'),
                                DatePicker::make('endDate')
                                    ->label('End Date'),
                                Textarea::make('summary')
                                    ->label('Description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                TagsInput::make('highlights')
                                    ->label('Key Achievements')
                                    ->placeholder('Add achievement')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['position'] ?? null)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible($this->showForm),

                // Education
                Section::make('Education')
                    ->schema([
                        Repeater::make('education')
                            ->schema([
                                TextInput::make('institution')
                                    ->label('Institution')
                                    ->required(),
                                TextInput::make('url')
                                    ->label('Institution Website')
                                    ->url(),
                                TextInput::make('area')
                                    ->label('Field of Study'),
                                TextInput::make('studyType')
                                    ->label('Degree Type'),
                                DatePicker::make('startDate')
                                    ->label('Start Date'),
                                DatePicker::make('endDate')
                                    ->label('End Date'),
                                TextInput::make('score')
                                    ->label('GPA/Score'),
                                TagsInput::make('courses')
                                    ->label('Relevant Courses')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['institution'] ?? null)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible($this->showForm),

                // Skills
                Section::make('Skills')
                    ->schema([
                        Repeater::make('skills')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Skill Category')
                                    ->required(),
                                TextInput::make('level')
                                    ->label('Proficiency Level')
                                    ->placeholder('Beginner, Intermediate, Advanced, Master'),
                                TagsInput::make('keywords')
                                    ->label('Skills')
                                    ->placeholder('Add skill')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible($this->showForm),

                // Projects
                Section::make('Projects')
                    ->schema([
                        Repeater::make('projects')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Project Name')
                                    ->required(),
                                TextInput::make('url')
                                    ->label('Project URL')
                                    ->url(),
                                DatePicker::make('startDate')
                                    ->label('Start Date'),
                                DatePicker::make('endDate')
                                    ->label('End Date'),
                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                TagsInput::make('highlights')
                                    ->label('Key Highlights')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible($this->showForm),

                // Languages
                Section::make('Languages')
                    ->schema([
                        Repeater::make('languages')
                            ->schema([
                                TextInput::make('language')
                                    ->label('Language')
                                    ->required(),
                                TextInput::make('fluency')
                                    ->label('Fluency Level')
                                    ->placeholder('Native, Fluent, Intermediate, Basic'),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible($this->showForm),

                // Certificates
                Section::make('Certificates')
                    ->schema([
                        Repeater::make('certificates')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Certificate Name')
                                    ->required(),
                                TextInput::make('issuer')
                                    ->label('Issuing Organization'),
                                DatePicker::make('date')
                                    ->label('Issue Date'),
                                TextInput::make('url')
                                    ->label('Certificate URL')
                                    ->url(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible($this->showForm),

                // Awards
                Section::make('Awards')
                    ->schema([
                        Repeater::make('awards')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Award Title')
                                    ->required(),
                                TextInput::make('awarder')
                                    ->label('Awarded By'),
                                DatePicker::make('date')
                                    ->label('Date'),
                                Textarea::make('summary')
                                    ->label('Description')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible($this->showForm),

                // Volunteer Experience
                Section::make('Volunteer Experience')
                    ->schema([
                        Repeater::make('volunteer')
                            ->schema([
                                TextInput::make('organization')
                                    ->label('Organization')
                                    ->required(),
                                TextInput::make('position')
                                    ->label('Role'),
                                TextInput::make('url')
                                    ->label('Organization Website')
                                    ->url(),
                                DatePicker::make('startDate')
                                    ->label('Start Date'),
                                DatePicker::make('endDate')
                                    ->label('End Date'),
                                Textarea::make('summary')
                                    ->label('Description')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                TagsInput::make('highlights')
                                    ->label('Key Contributions')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['organization'] ?? null)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible($this->showForm),

                // Publications
                Section::make('Publications')
                    ->schema([
                        Repeater::make('publications')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Publication Title')
                                    ->required(),
                                TextInput::make('publisher')
                                    ->label('Publisher'),
                                DatePicker::make('releaseDate')
                                    ->label('Release Date'),
                                TextInput::make('url')
                                    ->label('Publication URL')
                                    ->url(),
                                Textarea::make('summary')
                                    ->label('Summary')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible($this->showForm),

                // Interests
                Section::make('Interests')
                    ->schema([
                        Repeater::make('interests')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Interest')
                                    ->required(),
                                TagsInput::make('keywords')
                                    ->label('Keywords'),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible($this->showForm),

                // References
                Section::make('References')
                    ->schema([
                        Repeater::make('references')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Reference Name')
                                    ->required(),
                                Textarea::make('reference')
                                    ->label('Reference Text')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible($this->showForm),
            ])
            ->statePath('data');
    }

    protected function processResumeFile($file): void
    {
        try {
            $openAIService = app(OpenAIService::class);
            $parsedData = $openAIService->parseResumePdf($file->getRealPath());
            
            // Merge with empty structure to ensure all fields exist
            $parsedData = array_merge($this->getEmptyStructure(), $parsedData);
            
            Profile::updateOrCreate(
                ['user_id' => auth()->id()],
                ['data' => $parsedData]
            );
            
            $this->data = $parsedData;
            $this->showForm = true;
            $this->form->fill($this->data);
            
            Notification::make()
                ->success()
                ->title('Resume Processed')
                ->body('Your resume has been processed successfully. You can now edit the extracted data.')
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Processing Failed')
                ->body('Failed to process the resume: ' . $e->getMessage())
                ->send();
        }
    }

    protected function getEmptyStructure(): array
    {
        return [
            'basics' => [
                'name' => '',
                'label' => '',
                'image' => '',
                'email' => '',
                'phone' => '',
                'url' => '',
                'summary' => '',
                'location' => [
                    'address' => '',
                    'postalCode' => '',
                    'city' => '',
                    'countryCode' => '',
                    'region' => '',
                ],
                'profiles' => [],
            ],
            'work' => [],
            'volunteer' => [],
            'education' => [],
            'awards' => [],
            'certificates' => [],
            'publications' => [],
            'skills' => [],
            'languages' => [],
            'interests' => [],
            'references' => [],
            'projects' => [],
        ];
    }

    protected function getActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Resume Data')
                ->action(function () {
                    $formData = $this->form->getState();
                    
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
                ->visible($this->showForm),
        ];
    }
}